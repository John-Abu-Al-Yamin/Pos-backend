<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
    ) {}

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

            $totalLossAmount = 0;
            $totalGainAmount = 0;
            $unitCostSnapshot = 0;

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

                $totalLossAmount = $availableItems->sum(fn ($item) => (float) $item->cost_price);
                $unitCostSnapshot = $itemsToAdjust > 0
                    ? round($totalLossAmount / $itemsToAdjust, 2)
                    : 0;

                $this->ledger->recordInventoryLoss(
                    $product,
                    $itemsToAdjust,
                    $totalLossAmount,
                );
            } elseif ($difference > 0) {
                $unitCost = (float) ($data['unit_cost'] ?? 0);
                if (!($unitCost > 0)) {
                    throw new \RuntimeException('تكلفة الوحدة يجب أن تكون أكبر من 0 عند زيادة المخزون.');
                }

                $unitCostSnapshot = $unitCost;
                $newStockItems = [];
                for ($i = 0; $i < $difference; $i++) {
                    $newStockItems[] = [
                        'product_id' => $product->id,
                        'purchase_item_id' => null,
                        'serial_number' => null,
                        'cost_price' => $unitCost,
                        'condition' => 'fair',
                        'status' => 'available',
                        'notes' => 'Created via inventory adjustment',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                StockItem::insert($newStockItems);

                $totalGainAmount = $unitCost * $difference;

                $this->ledger->recordInventoryGain(
                    $product,
                    $difference,
                    $totalGainAmount,
                );
            }

            $adjustment = InventoryAdjustment::create([
                'product_id' => $product->id,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'difference' => $difference,
                'total_loss_amount' => $totalLossAmount,
                'total_gain_amount' => $totalGainAmount,
                'unit_cost_snapshot' => $unitCostSnapshot,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $adjustment->load(['product', 'createdBy']);

            return $adjustment;
        });
    }
}
