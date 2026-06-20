<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUser;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    //


    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {

            return ApiResponse::error(
                message: 'بيانات الدخول غير صحيحة',
                statusCode: 401
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success(
            data: [
                'user' => $user,
                'token' => $token,
            ],
            message: 'تم تسجيل الدخول بنجاح'
        );
    }

    public function logout(Request $request)
    {
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

    public function createUser(CreateUser $request)
    {
        $data = $request->validated();

        $user = User::create([
            "name" => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'employee', // Set the default role to 'employee'
        ]);

        return ApiResponse::success(
            data: $user,
            message: 'تم إنشاء المستخدم بنجاح'
        );
    }
}
