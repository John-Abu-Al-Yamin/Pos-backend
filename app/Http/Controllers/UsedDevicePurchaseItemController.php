<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsedDevicePurchaseItem\StoreUsedDevicePurchaseItemRequest;
use App\Http\Requests\UsedDevicePurchaseItem\UpdateUsedDevicePurchaseItemRequest;
use App\Http\Responses\ApiResponse;
use App\Models\UsedDevicePurchaseHeader;
use App\Models\UsedDevicePurchaseItem;
use App\Services\PurchaseUsed\UsedDevicePurchaseItemService;
use Illuminate\Http\Request;

class UsedDevicePurchaseItemController extends Controller
{
    public function __construct(
        private UsedDevicePurchaseItemService $usedDevicePurchaseItemService
    ) {}

    public function store(StoreUsedDevicePurchaseItemRequest $request, UsedDevicePurchaseHeader $purchase)
    {
        try {
            $item = $this->usedDevicePurchaseItemService->addItem(
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
            message: 'تم إنشاء صنف الشراء بنجاح',
            data: $item
        );
    }

    public function update(
        UpdateUsedDevicePurchaseItemRequest $request,
        UsedDevicePurchaseHeader $purchase,
        UsedDevicePurchaseItem $item
    ) {
        if ($item->used_device_purchase_header_id !== $purchase->id) {
            return ApiResponse::error(
                message: 'صنف الشراء غير تابع لهذه الفاتورة',
                statusCode: 404
            );
        }

        try {
            $item = $this->usedDevicePurchaseItemService->updateItem(
                $item,
                $request->validated()
            );
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم تحديث صنف الشراء بنجاح',
            data: $item
        );
    }

    public function index(Request $request, UsedDevicePurchaseHeader $purchase)
    {
        $perPage = (int) $request->input('per_page', 10);
        $items = $purchase->items()->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب أصناف الشراء بنجاح',
            data: $items
        );
    }

    public function show(UsedDevicePurchaseHeader $purchase, UsedDevicePurchaseItem $item)
    {
        if ($item->used_device_purchase_header_id !== $purchase->id) {
            return ApiResponse::error(
                message: 'صنف الشراء غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب صنف الشراء بنجاح',
            data: $item
        );
    }

    public function destroy(UsedDevicePurchaseHeader $purchase, UsedDevicePurchaseItem $item)
    {
        if ($item->used_device_purchase_header_id !== $purchase->id) {
            return ApiResponse::error(
                message: 'صنف الشراء غير تابع لهذه الفاتورة',
                statusCode: 404
            );
        }

        try {
            $this->usedDevicePurchaseItemService->deleteItem($item);
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم حذف صنف الشراء بنجاح'
        );
    }
}
