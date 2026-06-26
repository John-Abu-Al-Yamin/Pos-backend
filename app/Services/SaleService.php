<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function createSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'date' => $data['date'] ?? now()->format('Y-m-d'),
                'payment_method' => $data['payment_method'] ?? 'cash',
            ]);

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = (int) ($itemData['quantity'] ?? 1);
                $unitPrice = (float) ($itemData['unit_price'] ?? 0);
                $lineTotal = $quantity * $unitPrice;

                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                if ($product->is_serialized) {
                    $stockItemIds = $itemData['stock_item_ids'] ?? [];
                    if (count($stockItemIds) !== 1) {
                        throw new \RuntimeException("Serialized product '{$product->name}' requires exactly one stock_item_id.");
                    }

                    $stockItem = StockItem::lockForUpdate()->findOrFail($stockItemIds[0]);
                    if ($stockItem->status !== 'available') {
                        throw new \RuntimeException("Stock item #{$stockItem->id} is not available (status: {$stockItem->status}).");
                    }

                    $stockItem->update(['status' => 'sold']);
                    $saleItem->stockItems()->attach($stockItem->id);
                } else {
                    $availableStock = StockItem::where('product_id', $product->id)
                        ->where('status', 'available')
                        ->lockForUpdate()
                        ->orderBy('id')
                        ->take($quantity)
                        ->get();

                    if ($availableStock->count() < $quantity) {
                        throw new \RuntimeException(
                            "Insufficient stock for '{$product->name}'. Required: {$quantity}, Available: {$availableStock->count()}."
                        );
                    }

                    $ids = $availableStock->pluck('id')->toArray();
                    StockItem::whereIn('id', $ids)->update(['status' => 'sold']);
                    $saleItem->stockItems()->attach($ids);
                }
            }

            $sale->recalculateTotal();
            $sale->load(['customer', 'saleItems.product', 'saleItems.stockItems']);

            return $sale;
        });
    }
}
