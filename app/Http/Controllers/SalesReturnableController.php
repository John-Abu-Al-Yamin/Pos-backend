<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\SalesHeader;
use App\Models\SalesReturnItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnableController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);

        $query = SalesHeader::with('customer', 'createdBy')
            ->withCount('items')
            ->whereHas('items', function ($q) {
                $q->whereRaw('quantity > COALESCE((
                    SELECT SUM(sri.quantity)
                    FROM sales_return_items sri
                    WHERE sri.sales_item_id = sales_items.id
                ), 0)');
            });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $sales = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'Returnable sales retrieved successfully',
            data: $sales
        );
    }

    public function show(int $id)
    {
        $sale = SalesHeader::with([
            'customer',
            'createdBy',
            'items.product',
            'items.inventoryItem',
        ])->find($id);

        if (! $sale) {
            return ApiResponse::error(
                message: 'Sale not found',
                statusCode: 404
            );
        }

        $returnedQtyByItem = SalesReturnItem::query()
            ->whereIn('sales_item_id', $sale->items->pluck('id'))
            ->groupBy('sales_item_id')
            ->selectRaw('sales_item_id, SUM(quantity) as total_returned')
            ->pluck('total_returned', 'sales_item_id');

        $returnableItems = $sale->items->map(function ($item) use ($returnedQtyByItem) {
            $alreadyReturned = (int) ($returnedQtyByItem->get($item->id) ?? 0);
            $returnableQty = max(0, $item->quantity - $alreadyReturned);

            return [
                'sales_item_id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'type' => $item->product->type,
                ],
                'inventory_item' => $item->inventoryItem ? [
                    'id' => $item->inventoryItem->id,
                    'internal_serial' => $item->inventoryItem->internal_serial,
                ] : null,
                'quantity_sold' => $item->quantity,
                'already_returned_qty' => $alreadyReturned,
                'returnable_qty' => $returnableQty,
                'unit_price' => $item->unit_price,
            ];
        })->filter(fn ($item) => $item['returnable_qty'] > 0)
          ->values();

        return ApiResponse::success(data: [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'customer' => $sale->customer,
            'invoice_date' => $sale->created_at->format('Y-m-d'),
            'total_amount' => $sale->total_amount,
            'items_count' => $returnableItems->count(),
            'items' => $returnableItems,
        ]);
    }
}
