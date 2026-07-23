<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryAssignment\StoreSalaryAssignmentRequest;
use App\Http\Requests\SalaryAssignment\UpdateSalaryAssignmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SalaryAssignment;
use App\Models\User;
use App\Services\Salary\SalaryAssignmentService;
use DomainException;
use Illuminate\Http\Request;

class SalaryAssignmentController extends Controller
{
    public function __construct(
        private readonly SalaryAssignmentService $salaryAssignmentService
    ) {}

    public function index(Request $request)
    {
        $query = SalaryAssignment::with(['user', 'creator'])->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->forUser((int) $request->user_id);
        }

        return ApiResponse::success(
            message: 'تم جلب تخصيصات الرواتب بنجاح',
            data: $query->paginate(min((int) ($request->per_page ?? 20), 100))
        );
    }

    public function store(StoreSalaryAssignmentRequest $request)
    {
        $user = User::findOrFail($request->user_id);

        try {
            $assignment = $this->salaryAssignmentService->assignSalary($user, $request->validated());
            $assignment->load(['user', 'creator']);

            return ApiResponse::success(
                message: 'تم إنشاء تخصيص الراتب بنجاح',
                data: $assignment,
                statusCode: 201
            );
        } catch (DomainException $e) {
            return ApiResponse::error(message: $e->getMessage(), statusCode: 422);
        }
    }

    public function show(int $id)
    {
        $assignment = SalaryAssignment::with(['user', 'creator'])->find($id);

        if (!$assignment) {
            return ApiResponse::error(message: 'تخصيص الراتب غير موجود', statusCode: 404);
        }

        return ApiResponse::success(
            message: 'تم جلب تخصيص الراتب بنجاح',
            data: $assignment
        );
    }

    public function update(UpdateSalaryAssignmentRequest $request, int $id)
    {
        $assignment = SalaryAssignment::find($id);

        if (!$assignment) {
            return ApiResponse::error(message: 'تخصيص الراتب غير موجود', statusCode: 404);
        }

        try {
            $assignment = $this->salaryAssignmentService->updateAssignment(
                $assignment,
                $request->validated()
            );
            $assignment->load(['user', 'creator']);

            return ApiResponse::success(
                message: 'تم تحديث تخصيص الراتب بنجاح',
                data: $assignment
            );
        } catch (DomainException $e) {
            return ApiResponse::error(message: $e->getMessage(), statusCode: 422);
        }
    }

    public function destroy(int $id)
    {
        $assignment = SalaryAssignment::find($id);

        if (!$assignment) {
            return ApiResponse::error(message: 'تخصيص الراتب غير موجود', statusCode: 404);
        }

        if ($assignment->payments()->exists()) {
            return ApiResponse::error(
                message: 'لا يمكن حذف التخصيص لوجود مدفوعات مرتبطة به.',
                statusCode: 422
            );
        }

        $assignment->delete();

        return ApiResponse::success(message: 'تم حذف تخصيص الراتب بنجاح');
    }
}
