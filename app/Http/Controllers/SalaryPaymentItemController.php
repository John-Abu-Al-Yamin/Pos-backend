<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryPaymentItem\StoreSalaryPaymentItemRequest;
use App\Http\Requests\SalaryPaymentItem\UpdateSalaryPaymentItemRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SalaryPayment;
use Illuminate\Support\Facades\DB;

class SalaryPaymentItemController extends Controller
{
    public function index(int $paymentId)
    {
        $payment = SalaryPayment::find($paymentId);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        return ApiResponse::success(
            message: 'تم جلب بنود الدفعة بنجاح',
            data: $payment->items()->orderBy('created_at')->get()
        );
    }

    public function store(StoreSalaryPaymentItemRequest $request, int $paymentId)
    {
        $payment = SalaryPayment::find($paymentId);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        if (!$payment->isDraft()) {
            return ApiResponse::error(message: 'يمكن إضافة بنود للمدفوعات المسودة فقط.', statusCode: 422);
        }

        $item = DB::transaction(function () use ($payment, $request) {
            $item = $payment->items()->create($request->validated());

            $payment->fresh()->recalculateTotal();

            return $item;
        });

        return ApiResponse::success(
            message: 'تم إضافة بند الدفعة بنجاح',
            data: $item,
            statusCode: 201
        );
    }

    public function show(int $paymentId, int $itemId)
    {
        $payment = SalaryPayment::find($paymentId);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        $item = $payment->items()->find($itemId);

        if (!$item) {
            return ApiResponse::error(message: 'بند الدفعة غير موجود', statusCode: 404);
        }

        return ApiResponse::success(
            message: 'تم جلب بند الدفعة بنجاح',
            data: $item
        );
    }

    public function update(UpdateSalaryPaymentItemRequest $request, int $paymentId, int $itemId)
    {
        $payment = SalaryPayment::find($paymentId);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        if (!$payment->isDraft()) {
            return ApiResponse::error(message: 'يمكن تعديل بنود المدفوعات المسودة فقط.', statusCode: 422);
        }

        $item = $payment->items()->find($itemId);

        if (!$item) {
            return ApiResponse::error(message: 'بند الدفعة غير موجود', statusCode: 404);
        }

        $validated = $request->validated();

        if ($item->type === 'base_salary' && isset($validated['type']) && $validated['type'] !== 'base_salary') {
            return ApiResponse::error(message: 'لا يمكن تغيير نوع بند الراتب الأساسي.', statusCode: 422);
        }

        DB::transaction(function () use ($item, $validated, $payment) {
            $item->update($validated);

            $payment->fresh()->recalculateTotal();
        });

        return ApiResponse::success(
            message: 'تم تحديث بند الدفعة بنجاح',
            data: $item->fresh()
        );
    }

    public function destroy(int $paymentId, int $itemId)
    {
        $payment = SalaryPayment::find($paymentId);

        if (!$payment) {
            return ApiResponse::error(message: 'دفعة الراتب غير موجودة', statusCode: 404);
        }

        if (!$payment->isDraft()) {
            return ApiResponse::error(message: 'يمكن حذف بنود المدفوعات المسودة فقط.', statusCode: 422);
        }

        $item = $payment->items()->find($itemId);

        if (!$item) {
            return ApiResponse::error(message: 'بند الدفعة غير موجود', statusCode: 404);
        }

        DB::transaction(function () use ($item, $payment) {
            $item->delete();

            $payment->fresh()->recalculateTotal();
        });

        return ApiResponse::success(message: 'تم حذف بند الدفعة بنجاح');
    }
}
