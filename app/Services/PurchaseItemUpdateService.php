<?php

namespace App\Services;

use App\Exceptions\PurchaseItemUpdateException;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class PurchaseItemUpdateService
{
    public function __construct(
        private readonly SerialNumberService $serialNumberService
    ) {}

    /**
     * Update a purchase_item and reconcile its stock_items transactionally.
     *
     * Design decisions (Scenarios 1c, 5, 6):
     *
     * 1c — All units already sold: The purchase_item record itself is updated
     * (cost/condition), but no stock_items are changed. The response
     * communicates this via partial-success messaging. This is acceptable
     * because the purchase_item represents the agreement with the supplier;
     * the stock_items represent physical units that may have already moved
     * through inventory. Recording the updated cost on the purchase_item
     * keeps procurement records accurate without retroactively altering
     * completed transaction costs.
     *
     * 5 — damaged/returned are NOT removable: They represent historical
     * events (a unit was found damaged on arrival, or was returned by a
     * customer) that cannot be undone by a quantity reduction. Only
     * available stock_items count toward the "removable" pool. The minimum
     * quantity for a reduction = total stock_items - available stock_items
     * (i.e., the count of non-available units).
     *
     * 6 — Deletion guard: Deletion of a purchase_item is blocked if ANY
     * of its stock_items have a status other than 'available'. This
     * prevents orphaned references in sales records. Only purchase_items
     * whose stock is still entirely available can be safely removed.
     *
     * @return array{item: PurchaseItem, messages: string[]}
     */
    public function update(PurchaseItem $item, array $data): array
    {
        $product = $item->product;

        if (!$product->is_serialized && isset($data['condition'])) {
            $data['condition'] = 'new';
        }

        return DB::transaction(function () use ($item, $data, $product) {
            // Re-read purchase_item with row-level lock to get the latest
            // committed values and prevent race conditions (Scenario 7).
            $lockedItem = PurchaseItem::lockForUpdate()->findOrFail($item->id);

            // Lock all stock_items for this purchase_item concurrently.
            $lockedItem->stockItems()->lockForUpdate()->get();

            $oldQuantity = $lockedItem->quantity;
            $oldUnitCost = $lockedItem->unit_cost;
            $oldCondition = $lockedItem->condition;

            $newQuantity = $data['quantity'] ?? $oldQuantity;
            $newUnitCost = $data['unit_cost'] ?? $oldUnitCost;
            $newCondition = $data['condition'] ?? $oldCondition;

            $quantityChanged = $newQuantity !== $oldQuantity;
            $costChanged = $newUnitCost !== $oldUnitCost;
            $conditionChanged = $newCondition !== $oldCondition;

            $messages = [];

            // ---- Scenario 4 & 5: Quantity decrease ----
            if ($quantityChanged && $newQuantity < $oldQuantity) {
                $itemsToRemove = $oldQuantity - $newQuantity;

                $availableCount = StockItem::where('purchase_item_id', $lockedItem->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->count();

                if ($availableCount < $itemsToRemove) {
                    $minPossible = $oldQuantity - $availableCount;
                    throw new PurchaseItemUpdateException(
                        "Cannot reduce quantity to {$newQuantity} — {$itemsToRemove} unit(s) need to be removed, but only {$availableCount} are available. Minimum quantity is {$minPossible}."
                    );
                }

                // Delete the most-recently-created available items first (deterministic).
                $toDelete = StockItem::where('purchase_item_id', $lockedItem->id)
                    ->where('status', 'available')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->take($itemsToRemove)
                    ->pluck('id');

                StockItem::whereIn('id', $toDelete)->delete();

                $messages[] = "{$itemsToRemove} unit(s) deleted from stock.";
            }

            // ---- Scenario 3: Quantity increase ----
            if ($quantityChanged && $newQuantity > $oldQuantity) {
                $itemsToAdd = $newQuantity - $oldQuantity;
                $this->createAdditionalStockItems($lockedItem, $itemsToAdd, $newUnitCost, $newCondition, $product);
                $messages[] = "{$itemsToAdd} new stock item(s) created.";
            }

            // ---- Scenario 1 & 2: Cost / condition propagation ----
            if ($costChanged || $conditionChanged) {
                $availableForUpdate = StockItem::where('purchase_item_id', $lockedItem->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->get();
                $totalStockCount = StockItem::where('purchase_item_id', $lockedItem->id)
                    ->whereIn('status', ['available', 'sold', 'reserved', 'damaged', 'returned'])
                    ->count();

                $affectedCount = 0;
                foreach ($availableForUpdate as $si) {
                    $updateData = [];
                    if ($costChanged) {
                        $updateData['cost_price'] = $newUnitCost;
                    }
                    if ($conditionChanged) {
                        $updateData['condition'] = $newCondition;
                    }
                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        $si->update($updateData);
                        $affectedCount++;
                    }
                }

                $parts = [];
                if ($costChanged) {
                    $parts[] = 'cost';
                }
                if ($conditionChanged) {
                    $parts[] = 'condition';
                }
                $fieldLabel = implode('/', $parts);

                $nonAvailableCount = $totalStockCount - $affectedCount;

                if ($affectedCount > 0 && $nonAvailableCount > 0) {
                    $messages[] = "{$fieldLabel} updated on {$affectedCount} available unit(s); {$nonAvailableCount} non-available unit(s) left unchanged.";
                } elseif ($affectedCount > 0) {
                    $messages[] = "{$fieldLabel} updated on all {$affectedCount} unit(s).";
                } elseif ($nonAvailableCount > 0) {
                    $messages[] = "No {$fieldLabel} changes applied — all units are already sold, reserved, damaged, or returned.";
                } else {
                    $messages[] = "No {$fieldLabel} changes applied — no stock items found.";
                }
            }

            // ---- Update the purchase_item record ----
            $lockedItem->update([
                'quantity' => $newQuantity,
                'unit_cost' => $newUnitCost,
                'condition' => $newCondition,
                'line_total' => $newQuantity * $newUnitCost,
            ]);

            // ---- Scenario 8: Recalculate header total ----
            $lockedItem->purchaseHeader->recalculateTotal();

            $lockedItem->load(['purchaseHeader', 'product', 'stockItems']);

            return [
                'item' => $lockedItem,
                'messages' => $messages,
            ];
        });
    }

    private function createAdditionalStockItems(
        PurchaseItem $item,
        int $count,
        float $costPrice,
        string $condition,
        $product
    ): void {
        $records = [];
        $serialNumbers = [];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            $record = [
                'product_id' => $item->product_id,
                'purchase_item_id' => $item->id,
                'cost_price' => $costPrice,
                'condition' => $condition,
                'status' => 'available',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($product->is_serialized) {
                do {
                    $serialNumber = $this->serialNumberService->generate($product);
                } while (isset($serialNumbers[$serialNumber]));
                $serialNumbers[$serialNumber] = true;
                $record['serial_number'] = $serialNumber;
            }

            $records[] = $record;
        }

        StockItem::insert($records);
    }
}
