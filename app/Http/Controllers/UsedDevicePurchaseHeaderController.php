<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsedDevicePurchaseHeader\StoreUsedDevicePurchaseHeaderRequest;
use App\Http\Requests\UsedDevicePurchaseHeader\UpdateUsedDevicePurchaseHeaderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\UsedDevicePurchaseHeader;
use App\Services\PurchaseUsed\PurchaseUsedDeviceService;
use Illuminate\Http\Request;

class UsedDevicePurchaseHeaderController extends Controller
{
    //
        public function __construct(
        private PurchaseUsedDeviceService $purchaseUsedDeviceService
        ) {}

    public function store(StoreUsedDevicePurchaseHeaderRequest $request)
    {
        $purchase = $this->purchaseUsedDeviceService->createDraft(
            $request->validated()
        );
        return ApiResponse::success(
            message: 'تم إنشاء فاتورة شراء الأجهزة المستعملة بنجاح',
            data: $purchase
        );
    }

    public function update(UpdateUsedDevicePurchaseHeaderRequest $request, int $id)
    {
        $purchase = UsedDevicePurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة شراء الأجهزة المستعملة غير موجودة',
                statusCode: 404
            );
        }

        try {
            $purchase = $this->purchaseUsedDeviceService->updateDraft(
                $purchase,
                $request->validated()
            );
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم تحديث فاتورة شراء الأجهزة المستعملة بنجاح',
            data: $purchase
        );
    }

    public function cancel(int $id)
    {
        $purchase = UsedDevicePurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة شراء الأجهزة المستعملة غير موجودة',
                statusCode: 404
            );
        }

        try {
            $this->purchaseUsedDeviceService->cancel($purchase);
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم إلغاء فاتورة شراء الأجهزة المستعملة بنجاح',
            data: $purchase
        );
    }

    public function complete(int $id)
    {
        $purchase = UsedDevicePurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة شراء الأجهزة المستعملة غير موجودة',
                statusCode: 404
            );
        }

        try {
            $this->purchaseUsedDeviceService->complete($purchase);
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم إكمال فاتورة شراء الأجهزة المستعملة بنجاح',
            data: $purchase->fresh()
        );
    }
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $purchases = UsedDevicePurchaseHeader::with(['customer', 'createdBy'])->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب فواتير شراء الأجهزة المستعملة بنجاح',
            data: $purchases
        );
    }

        public function show(int $id)
    {
        $purchase = UsedDevicePurchaseHeader::with(['customer', 'createdBy'])->find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة شراء الأجهزة المستعملة غير موجودة',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب فاتورة شراء الأجهزة المستعملة بنجاح',
            data: $purchase
        );
    }

    public function destroy(int $id)
    {
        $purchase = UsedDevicePurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة شراء الأجهزة المستعملة غير موجودة',
                statusCode: 404
            );
        }

        if (!$purchase->isDraft()) {
            return ApiResponse::error(
                message: 'لا يمكن حذف فاتورة مكتملة أو ملغاة.',
                statusCode: 400
            );
        }

        $purchase->delete();

        return ApiResponse::success(
            message: 'تم حذف فاتورة شراء الأجهزة المستعملة بنجاح'
        );
    }

}
