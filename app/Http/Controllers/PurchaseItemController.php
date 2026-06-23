<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseItem\StorePurchaseItemRequest;
use App\Http\Requests\PurchaseItem\UpdatePurchaseItemRequest;
use App\Http\Responses\ApiResponse;
use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;
use App\Services\StockItemService;
use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    public function __construct(
        private readonly StockItemService $stockItemService
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $items = PurchaseItem::with(['purchaseHeader', 'product'])->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب عناصر الشراء بنجاح',
            data: $items
        );
    }

    public function store(StorePurchaseItemRequest $request)
    {
        $data = $request->validated();
        $data['line_total'] = $data['quantity'] * $data['unit_cost'];

        $item = PurchaseItem::create($data);
        $this->stockItemService->createFromPurchaseItem($item);
        $item->load(['purchaseHeader', 'product', 'stockItems']);
        $item->purchaseHeader->recalculateTotal();

        return ApiResponse::success(
            message: 'تم إنشاء عنصر الشراء بنجاح',
            data: $item,
            statusCode: 201
        );
    }

    public function show(int $id)
    {
        $item = PurchaseItem::with(['purchaseHeader', 'product'])->find($id);

        if (!$item) {
            return ApiResponse::error(
                message: 'عنصر الشراء غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب عنصر الشراء بنجاح',
            data: $item
        );
    }

    public function update(UpdatePurchaseItemRequest $request, int $id)
    {
        $item = PurchaseItem::find($id);

        if (!$item) {
            return ApiResponse::error(
                message: 'عنصر الشراء غير موجود',
                statusCode: 404
            );
        }

        $data = $request->validated();

        if (isset($data['quantity']) || isset($data['unit_cost'])) {
            $quantity = $data['quantity'] ?? $item->quantity;
            $unitCost = $data['unit_cost'] ?? $item->unit_cost;
            $data['line_total'] = $quantity * $unitCost;
        }

        $item->update($data);
        $item->load(['purchaseHeader', 'product']);
        $item->purchaseHeader->recalculateTotal();

        return ApiResponse::success(
            message: 'تم تحديث عنصر الشراء بنجاح',
            data: $item
        );
    }

    public function destroy(int $id)
    {
        $item = PurchaseItem::find($id);

        if (!$item) {
            return ApiResponse::error(
                message: 'عنصر الشراء غير موجود',
                statusCode: 404
            );
        }

        $headerId = $item->purchase_header_id;
        $item->delete();
        PurchaseHeader::find($headerId)?->recalculateTotal();

        return ApiResponse::success(
            message: 'تم حذف عنصر الشراء بنجاح'
        );
    }
}
