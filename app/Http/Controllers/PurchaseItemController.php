<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseItem\StorePurchaseItemRequest;
use App\Http\Requests\PurchaseItem\UpdatePurchaseItemRequest;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use App\Models\PurchaseItem;
use App\Models\PurchaseHeader;
use App\Services\Purchase\PurchaseItemService;

class PurchaseItemController extends Controller
{
    public function __construct(
        private PurchaseItemService $purchaseItemService
    ) {}

    public function store(StorePurchaseItemRequest $request, PurchaseHeader $purchase)
    {
        try {
            $item = $this->purchaseItemService->addItem(
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
        UpdatePurchaseItemRequest $request,
        PurchaseHeader $purchase,
        PurchaseItem $item
    ) {
        if ($item->purchase_header_id !== $purchase->id) {
            return ApiResponse::error(
                message: 'صنف الشراء غير تابع لهذه الفاتورة',
                statusCode: 404
            );
        }

        try {
            $item = $this->purchaseItemService->updateItem(
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

    public function index(Request $request, PurchaseHeader $purchase)
    {
        $perPage = (int) $request->input('per_page', 10);
        $items = $purchase->items()->paginate($perPage);
        return ApiResponse::success(
            message: 'تم جلب أصناف الشراء بنجاح',
            data: $items
        );
    }

    public function show(PurchaseHeader $purchase, PurchaseItem $item)
    {
        if ($item->purchase_header_id !== $purchase->id) {
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

    public function destroy(PurchaseHeader $purchase, PurchaseItem $item)
    {
        if ($item->purchase_header_id !== $purchase->id) {
            return ApiResponse::error(
                message: 'صنف الشراء غير تابع لهذه الفاتورة',
                statusCode: 404
            );
        }

        try {
            $this->purchaseItemService->deleteItem($item);
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
