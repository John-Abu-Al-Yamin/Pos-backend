<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\PurchaseHeader;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class PurchaseReturnableController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);

        $query = PurchaseHeader::with('supplier', 'createdBy')
            ->withCount('items')
            ->where('status', 'completed')
            ->whereHas('items', function ($q) {
                $q->whereRaw('quantity > COALESCE((
                    SELECT SUM(pri.quantity)
                    FROM purchase_return_items pri
                    WHERE pri.purchase_item_id = purchase_items.id
                ), 0)');
            });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('purchaseHeader_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $purchases = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'Returnable purchases retrieved successfully',
            data: $purchases
        );
    }

    public function show(int $id)
    {
        $purchase = PurchaseHeader::with([
            'supplier',
            'createdBy',
            'items.product',
        ])->find($id);

        if (! $purchase) {
            return ApiResponse::error(
                message: 'Purchase not found',
                statusCode: 404
            );
        }

        $returnedQtyByItem = PurchaseReturnItem::query()
            ->whereIn('purchase_item_id', $purchase->items->pluck('id'))
            ->groupBy('purchase_item_id')
            ->selectRaw('purchase_item_id, SUM(quantity) as total_returned')
            ->pluck('total_returned', 'purchase_item_id');

        $inventoryItemIds = StockMovement::where('reference_type', PurchaseHeader::class)
            ->where('reference_id', $purchase->id)
            ->whereNotNull('inventory_item_id')
            ->pluck('inventory_item_id');

        $availableInventoryItems = InventoryItem::whereIn('id', $inventoryItemIds)
            ->where('status', 'available')
            ->get()
            ->groupBy('product_id');

        $productIds = $purchase->items->pluck('product_id');
        $inventoryQuantities = InventoryQuantity::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $returnableItems = collect();

        foreach ($purchase->items as $item) {
            if ($item->product->type === 'mobile') {
                $itemInventoryItems = $availableInventoryItems->get($item->product_id, collect());

                foreach ($itemInventoryItems as $invItem) {
                    $returnableItems->push([
                        'purchase_item_id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'type' => $item->product->type,
                        ],
                        'inventory_item' => [
                            'id' => $invItem->id,
                            'internal_serial' => $invItem->internal_serial,
                        ],
                        'quantity_purchased' => 1,
                        'already_returned_qty' => 0,
                        'returnable_qty' => 1,
                        'unit_price' => $item->unit_price,
                    ]);
                }
            } else {
                $alreadyReturned = (int) ($returnedQtyByItem->get($item->id) ?? 0);
                $remainingFromPurchase = max(0, $item->quantity - $alreadyReturned);
                $currentStock = (int) ($inventoryQuantities->get($item->product_id)?->quantity ?? 0);
                $returnableQty = min($remainingFromPurchase, $currentStock);

                if ($returnableQty > 0) {
                    $returnableItems->push([
                        'purchase_item_id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'type' => $item->product->type,
                        ],
                        'inventory_item' => null,
                        'quantity_purchased' => $item->quantity,
                        'already_returned_qty' => $alreadyReturned,
                        'returnable_qty' => $returnableQty,
                        'unit_price' => $item->unit_price,
                    ]);
                }
            }
        }

        return ApiResponse::success(data: [
            'id' => $purchase->id,
            'purchaseHeader_number' => $purchase->purchaseHeader_number,
            'supplier' => $purchase->supplier,
            'invoice_date' => $purchase->created_at->format('Y-m-d'),
            'total_amount' => $purchase->total_amount,
            'items_count' => $returnableItems->count(),
            'items' => $returnableItems,
        ]);
    }
}
