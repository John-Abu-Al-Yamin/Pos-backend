<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseItem\StorePurchaseItemRequest;
use App\Http\Requests\PurchaseItem\UpdatePurchaseItemRequest;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use App\Models\PurchaseItem;
use App\Services\Purchase\PurchaseItemService;

class PurchaseItemController extends Controller
{
    public function __construct(
        private PurchaseItemService $purchaseItemService
    ) {}

    public function store(StorePurchaseItemRequest $request)
    {
        try {
            $item = $this->purchaseItemService->addItem(
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

    public function update(UpdatePurchaseItemRequest $request, int $id)
    {
        $item = PurchaseItem::find($id);

        if (!$item) {
            return ApiResponse::error(
                message: 'صنف الشراء غير موجود',
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

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $query = PurchaseItem::with('product');

        if ($request->filled('purchase_header_id')) {
            $query->where('purchase_header_id', $request->input('purchase_header_id'));
        }

        $items = $query->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب أصناف الشراء بنجاح',
            data: $items
        );
    }

    public function show(int $id)
    {
        $item = PurchaseItem::with('product')->find($id);

        if (!$item) {
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

    public function destroy(int $id)
    {
        $item = PurchaseItem::find($id);

        if (!$item) {
            return ApiResponse::error(
                message: 'صنف الشراء غير موجود',
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
