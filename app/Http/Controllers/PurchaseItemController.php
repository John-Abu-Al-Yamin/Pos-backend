<?php

namespace App\Http\Controllers;

use App\Exceptions\PurchaseItemUpdateException;
use App\Http\Requests\PurchaseItem\StorePurchaseItemRequest;
use App\Http\Requests\PurchaseItem\UpdatePurchaseItemRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use App\Services\PurchaseItemUpdateService;
use App\Services\StockItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseItemController extends Controller
{
    public function __construct(
        private readonly StockItemService $stockItemService,
        private readonly PurchaseItemUpdateService $purchaseItemUpdateService,
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $items = PurchaseItem::with(['purchaseHeader', 'product', 'stockItems'])->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب عناصر الشراء بنجاح',
            data: $items
        );
    }

    public function store(StorePurchaseItemRequest $request)
    {
        $data = $request->validated();
        $data['line_total'] = $data['quantity'] * $data['unit_cost'];

        $product = Product::findOrFail($data['product_id']);
        if (!$product->is_serialized) {
            $data['condition'] = 'new';
        }

        $deviceDetails = $data['device_details'] ?? [];
        unset($data['device_details']);

        $item = DB::transaction(function () use ($data, $deviceDetails) {
            $item = PurchaseItem::create($data);
            $this->stockItemService->createFromPurchaseItem($item, $deviceDetails);
            $item->load(['purchaseHeader', 'product', 'stockItems']);
            $item->purchaseHeader->recalculateTotal();
            return $item;
        });

        return ApiResponse::success(
            message: 'تم إنشاء عنصر الشراء بنجاح',
            data: $item,
            statusCode: 201
        );
    }

    public function show(int $id)
    {
        $item = PurchaseItem::with(['purchaseHeader', 'product', 'stockItems'])->find($id);

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

        try {
            $result = $this->purchaseItemUpdateService->update($item, $data);

            $response = ApiResponse::success(
                message: 'تم تحديث عنصر الشراء بنجاح',
                data: $result['item'],
            );

            if (!empty($result['messages'])) {
                $response->setData($response->getData(true) + ['update_messages' => $result['messages']]);
            }

            return $response;
        } catch (PurchaseItemUpdateException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 409
            );
        }
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

        DB::transaction(function () use ($item) {
            $lockedItem = PurchaseItem::lockForUpdate()->findOrFail($item->id);

            StockItem::where('purchase_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->delete();

            $headerId = $lockedItem->purchase_header_id;
            $lockedItem->delete();
            PurchaseHeader::find($headerId)?->recalculateTotal();
        });

        return ApiResponse::success(
            message: 'تم حذف عنصر الشراء بنجاح'
        );
    }
}
