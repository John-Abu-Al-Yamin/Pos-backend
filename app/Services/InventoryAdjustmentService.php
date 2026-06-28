<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    public function createAdjustment(array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);

            $quantityBefore = StockItem::where('product_id', $product->id)
                ->where('status', 'available')
                ->lockForUpdate()
                ->count();

            $quantityAfter = (int) $data['quantity_after'];
            $difference = $quantityAfter - $quantityBefore;

            if ($difference < 0) {
                $itemsToAdjust = abs($difference);
                $availableItems = StockItem::where('product_id', $product->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->take($itemsToAdjust)
                    ->get();

                if ($availableItems->count() < $itemsToAdjust) {
                    throw new \RuntimeException("لا يوجد عدد كافٍ من العناصر المتاحة للتسوية. المطلوب: {$itemsToAdjust}, المتاح: {$availableItems->count()}.");
                }

                $newStatus = match ($data['reason']) {
                    'Damaged', 'Broken' => 'damaged',
                    'Internal use' => 'consumed',
                    default => 'voided',
                };

                $ids = $availableItems->pluck('id')->toArray();
                StockItem::whereIn('id', $ids)->update(['status' => $newStatus]);
            } elseif ($difference > 0) {
                $newStockItems = [];
                for ($i = 0; $i < $difference; $i++) {
                    $newStockItems[] = [
                        'product_id' => $product->id,
                        'purchase_item_id' => null,
                        'serial_number' => null,
                        'cost_price' => 0,
                        'condition' => 'fair',
                        'status' => 'available',
                        'notes' => 'Created via inventory adjustment',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                StockItem::insert($newStockItems);
            }

            $adjustment = InventoryAdjustment::create([
                'product_id' => $product->id,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'difference' => $difference,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $adjustment->load(['product', 'createdBy']);

            return $adjustment;
        });
    }
}
