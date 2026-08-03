<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUser;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //

    public function login(LoginRequest $request, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $auditLogService->record(
                module: 'auth',
                action: 'login_failed',
                metadata: ['email' => $data['email']],
                status: 'failed',
                severity: 'warning',
            );

            return ApiResponse::error(
                message: 'بيانات الدخول غير صحيحة',
                statusCode: 401
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        auth()->setUser($user);

        $auditLogService->record(
            module: 'auth',
            action: 'login_success',
            auditable: $user,
            metadata: ['email' => $user->email],
        );

        return ApiResponse::success(
            data: [
                'user' => $user,
                'token' => $token,
            ],
            message: 'تم تسجيل الدخول بنجاح'
        );
    }

    public function logout(Request $request, AuditLogService $auditLogService)
    {
        $auditLogService->record(
            module: 'auth',
            action: 'logout',
            auditable: $request->user(),
        );

        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(
            message: 'تم تسجيل الخروج بنجاح'
        );
    }

    public function me(Request $request)
    {
        return ApiResponse::success(
            data: $request->user()
        );
    }

    public function createUser(CreateUser $request, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        $user = AuditLogService::withoutModelEvents(fn () => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'employee', // Set the default role to 'employee'
        ]));

        $auditLogService->record(
            module: 'users_roles',
            action: 'user_created',
            auditable: $user,
            metadata: [
                'created_user_id' => $user->id,
                'created_user_email' => $user->email,
                'created_user_role' => $user->role,
            ],
        );

        return ApiResponse::success(
            data: $user,
            message: 'تم إنشاء المستخدم بنجاح'
        );
    }

    public function index(Request $request)
    {
        $query = User::select(['id', 'name', 'email', 'role']);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        return ApiResponse::success(
            data: $query->paginate(min((int) ($request->per_page ?? 100), 500))
        );
    }
}
