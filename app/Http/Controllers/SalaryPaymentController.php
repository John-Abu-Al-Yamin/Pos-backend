<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryPayment\StoreSalaryPaymentRequest;
use App\Http\Requests\SalaryPayment\UpdateSalaryPaymentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SalaryPayment;
use App\Services\Salary\SalaryPaymentService;
use DomainException;
use Illuminate\Http\Request;

class SalaryPaymentController extends Controller
{
    public function __construct(
        private readonly SalaryPaymentService $salaryPaymentService
    ) {}

    public function index(Request $request)
    {
        $query = SalaryPayment::with(['user', 'assignment', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->forUser((int) $request->user_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        return ApiResponse::success(
            message: 'تم جلب مدفوعات الرواتب بنجاح',
            data: $query->paginate(min((int) ($request->per_page ?? 20), 100))
        );
    }

    public function store(StoreSalaryPaymentRequest $request)
    {
        try {
            $payment = $this->salaryPaymentService->createPayment($request->validated());
            $payment->load(['user', 'assignment', 'creator']);

            return ApiResponse::success(
                message: 'تم إنشاء دفعة الراتب بنجاح',
                data: $payment,
                statusCode: 201
            );
        } catch (DomainException $e) {
            return ApiResponse::error(message: $e->getMessage(), statusCode: 422);
        }
    }

    public function show(int $id)
    {
        $payment = SalaryPayment::with(['user', 'assignment', 'items', 'creator', 'confirmedBy'])->find($id);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        return ApiResponse::success(
            message: 'تم جلب دفعة الراتب بنجاح',
            data: $payment
        );
    }

    public function update(UpdateSalaryPaymentRequest $request, int $id)
    {
        $payment = SalaryPayment::find($id);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        if (!$payment->isDraft()) {
            return ApiResponse::error(message: 'يمكن تعديل المدفوعات المسودة فقط.', statusCode: 422);
        }

        $payment->update($request->validated());
        $payment->load(['user', 'assignment', 'creator']);

        return ApiResponse::success(
            message: 'تم تحديث دفعة الراتب بنجاح',
            data: $payment
        );
    }

    public function destroy(int $id)
    {
        $payment = SalaryPayment::find($id);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        if (!$payment->isDraft()) {
            return ApiResponse::error(message: 'يمكن إلغاء المدفوعات المسودة فقط.', statusCode: 422);
        }

        $payment->update(['status' => 'cancelled']);

        return ApiResponse::success(message: 'تم إلغاء دفعة الراتب بنجاح');
    }

    public function confirm(int $id)
    {
        $payment = SalaryPayment::find($id);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        try {
            $payment = $this->salaryPaymentService->confirmPayment($payment);
            $payment->load(['user', 'assignment', 'items', 'creator', 'confirmedBy']);

            return ApiResponse::success(
                message: 'تم تأكيد دفعة الراتب بنجاح',
                data: $payment
            );
        } catch (DomainException $e) {
            return ApiResponse::error(message: $e->getMessage(), statusCode: 422);
        }
    }

    public function cancel(int $id)
    {
        $payment = SalaryPayment::find($id);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        try {
            $payment = $this->salaryPaymentService->cancelPayment($payment);

            return ApiResponse::success(
                message: 'تم إلغاء دفعة الراتب بنجاح',
                data: $payment
            );
        } catch (DomainException $e) {
            return ApiResponse::error(message: $e->getMessage(), statusCode: 422);
        }
    }
}
