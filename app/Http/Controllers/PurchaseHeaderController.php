<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseHeader\StorePurchaseHeaderRequest;
use App\Http\Requests\PurchaseHeader\UpdatePurchaseHeaderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\PurchaseHeader;
use App\Services\Purchase\PurchaseHeaderService;
use Illuminate\Http\Request;

class PurchaseHeaderController extends Controller
{
    //
    public function __construct(
        private PurchaseHeaderService $purchaseHeaderService
    ) {}

    public function store(StorePurchaseHeaderRequest $request)
    {
        $purchase = $this->purchaseHeaderService->createDraft(
            $request->validated()
        );
        return ApiResponse::success(
            message: 'تم إنشاء فاتورة الشراء بنجاح',
            data: $purchase
        );
    }
    public function update(UpdatePurchaseHeaderRequest $request, int $id)
    {
        $purchase = PurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة الشراء غير موجودة',
                statusCode: 404
            );
        }

        try {
            $purchase = $this->purchaseHeaderService->updateDraft(
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
            message: 'تم تحديث فاتورة الشراء بنجاح',
            data: $purchase
        );
    }

    public function complete(int $id)
    {
        $purchase = PurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة الشراء غير موجودة',
                statusCode: 404
            );
        }

        try {
            $this->purchaseHeaderService->complete($purchase);
        } catch (\Exception $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم إكمال فاتورة الشراء بنجاح',
            data: $purchase->fresh()
        );
    }

    public function cancel(int $id)
    {
        $purchase = PurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة الشراء غير موجودة',
                statusCode: 404
            );
        }

        try {
            $this->purchaseHeaderService->cancel($purchase);
        } catch (\Exception $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم إلغاء فاتورة الشراء بنجاح',
            data: $purchase->fresh()
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $purchases = PurchaseHeader::with(['supplier', 'createdBy'])
            ->withCount('items')
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب فواتير الشراء بنجاح',
            data: $purchases
        );
    }

    public function show(int $id)
    {
        $purchase = PurchaseHeader::with(['supplier', 'createdBy', 'items.product'])->find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة الشراء غير موجودة',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب فاتورة الشراء بنجاح',
            data: $purchase
        );
    }

    public function destroy(int $id)
    {
        $purchase = PurchaseHeader::find($id);

        if (!$purchase) {
            return ApiResponse::error(
                message: 'فاتورة الشراء غير موجودة',
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
            message: 'تم حذف فاتورة الشراء بنجاح'
        );
    }
}
