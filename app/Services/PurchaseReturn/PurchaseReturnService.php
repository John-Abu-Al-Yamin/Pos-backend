<?php

namespace App\Services\PurchaseReturn;

use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\PurchaseHeader;
use App\Models\PurchaseReturnHeader;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function processReturn(array $data)
    {
        $return = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($data) {

            $purchase = PurchaseHeader::with('items.product')
                ->lockForUpdate()
                ->findOrFail($data['purchase_header_id']);

            if (! $purchase->isCompleted()) {
                throw new \DomainException('Only completed purchases can be returned.');
            }

            $returnedQtyByItem = PurchaseReturnItem::whereHas(
                'purchaseReturnHeader'
            )
                ->whereIn('purchase_item_id', $purchase->items->pluck('id'))
                ->groupBy('purchase_item_id')
                ->selectRaw('purchase_item_id, SUM(quantity) as total_returned')
                ->pluck('total_returned', 'purchase_item_id');

            $totalRefund = 0;
            $preparedItems = [];

            foreach ($data['items'] as $item) {

                $purchaseItem = $purchase->items->firstWhere('id', $item['purchase_item_id']);

                if (! $purchaseItem) {
                    throw new \RuntimeException('Purchase item not found in this invoice.');
                }

                if ((float) $item['unit_refund_amount'] < 0) {
                    throw new \RuntimeException('Refund amount cannot be negative.');
                }

                $unitRefundAmount = round((float) $item['unit_refund_amount'], 2);
                $allowedUnitRefundAmount = round((float) $purchaseItem->unit_price, 2);

                if ($unitRefundAmount > $allowedUnitRefundAmount) {
                    throw new \RuntimeException('Refund amount exceeds original purchase unit cost.');
                }

                if (isset($item['inventory_item_id'])) {
                    $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($item['inventory_item_id']);

                    if ($inventoryItem->status !== 'available') {
                        throw new \RuntimeException('Device is not in available status.');
                    }

                    $alreadyReturned = PurchaseReturnItem::where('inventory_item_id', $inventoryItem->id)
                        ->whereHas('purchaseReturnHeader')
                        ->exists();

                    if ($alreadyReturned) {
                        throw new \RuntimeException('This device has already been returned.');
                    }

                    $belongsToPurchase = StockMovement::where('reference_type', PurchaseHeader::class)
                        ->where('reference_id', $purchase->id)
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->exists();

                    if (! $belongsToPurchase) {
                        throw new \RuntimeException('This device does not belong to this purchase.');
                    }

                    if ($inventoryItem->product_id !== $purchaseItem->product_id) {
                        throw new \RuntimeException('Device product does not match purchase item product.');
                    }

                    $preparedItems[] = [
                        'type' => 'mobile',
                        'purchase_item_id' => $purchaseItem->id,
                        'product_id' => $purchaseItem->product_id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity' => 1,
                        'unit_refund_amount' => $unitRefundAmount,
                        'total_refund' => $unitRefundAmount,
                        'unit_cost' => (float) $inventoryItem->cost_price,
                        'inventory_item' => $inventoryItem,
                    ];

                    $totalRefund += $unitRefundAmount;
                } else {
                    $product = $purchaseItem->product;

                    if (! in_array($product->type, ['accessory', 'spare_part'])) {
                        throw new \RuntimeException('Product must be accessory or spare part.');
                    }

                    $alreadyReturned = (int) ($returnedQtyByItem->get($item['purchase_item_id']) ?? 0);
                    $returnableQty = $purchaseItem->quantity - $alreadyReturned;

                    if ($item['quantity'] > $returnableQty) {
                        throw new \RuntimeException('Return quantity exceeds remaining returnable quantity.');
                    }

                    $inventoryQuantity = InventoryQuantity::where('product_id', $product->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($item['quantity'] > $inventoryQuantity->quantity) {
                        throw new \RuntimeException('Not enough stock available for return.');
                    }

                    $preparedItems[] = [
                        'type' => 'quantity_product',
                        'purchase_item_id' => $purchaseItem->id,
                        'product_id' => $product->id,
                        'inventory_item_id' => null,
                        'quantity' => (int) $item['quantity'],
                        'unit_refund_amount' => $unitRefundAmount,
                        'total_refund' => round((int) $item['quantity'] * $unitRefundAmount, 2),
                        'unit_cost' => (float) $purchaseItem->unit_price,
                        'inventory_quantity' => $inventoryQuantity,
                    ];

                    $totalRefund += round((int) $item['quantity'] * $unitRefundAmount, 2);
                }
            }

            $return = PurchaseReturnHeader::create([
                'purchase_header_id' => $purchase->id,
                'return_number' => $this->generateReturnNumber(),
                'supplier_id' => $data['supplier_id'] ?? $purchase->supplier_id,
                'user_id' => auth()->id(),
                'total_refund_amount' => $totalRefund,
                'reason' => $data['reason'] ?? null,
                'return_date' => Carbon::parse($data['return_date'] ?? now())->toDateString(),
            ]);

            foreach ($preparedItems as $preparedItem) {

                PurchaseReturnItem::create([
                    'purchase_return_header_id' => $return->id,
                    'purchase_item_id' => $preparedItem['purchase_item_id'],
                    'product_id' => $preparedItem['product_id'],
                    'inventory_item_id' => $preparedItem['inventory_item_id'],
                    'quantity' => $preparedItem['quantity'],
                    'unit_refund_amount' => $preparedItem['unit_refund_amount'],
                    'unit_cost' => $preparedItem['unit_cost'],
                    'total_refund' => $preparedItem['total_refund'],
                ]);

                if ($preparedItem['type'] === 'mobile') {
                    $preparedItem['inventory_item']->update([
                        'status' => 'returned',
                    ]);
                } else {
                    $invQty = $preparedItem['inventory_quantity'];
                    $currentQty = $invQty->quantity;
                    $currentCost = (float) $invQty->cost_price;
                    $returnQty = $preparedItem['quantity'];
                    $returnCost = $preparedItem['unit_cost'];

                    $newQty = $currentQty - $returnQty;
                    $newAvgCost = $newQty > 0
                        ? (($currentQty * $currentCost) - ($returnQty * $returnCost)) / $newQty
                        : 0;
                    $newCost = round(max($newAvgCost, 0), 2);

                    $invQty->decrement('quantity', $returnQty);
                    $invQty->update(['cost_price' => $newCost]);

                    if (round($currentCost, 2) !== $newCost) {
                        app(AuditLogService::class)->record(
                            module: 'inventory',
                            action: 'inventory_cost_changed',
                            auditable: $invQty,
                            oldValues: ['cost_price' => round($currentCost, 2)],
                            newValues: ['cost_price' => $newCost],
                            changedFields: ['cost_price'],
                            metadata: [
                                'product_id' => $preparedItem['product_id'],
                                'old_cost' => round($currentCost, 2),
                                'new_cost' => $newCost,
                                'reason' => 'purchase_return_created',
                                'related_document_type' => PurchaseReturnHeader::class,
                                'related_document_id' => $return->id,
                                'reference_number' => $return->return_number,
                            ],
                            severity: 'warning',
                            deferUntilCommit: true,
                        );
                    }
                }

                StockMovement::create([
                    'product_id' => $preparedItem['product_id'],
                    'inventory_item_id' => $preparedItem['inventory_item_id'],
                    'movement_type' => 'purchase_return',
                    'movement' => 'out',
                    'quantity' => $preparedItem['quantity'],
                    'unit_cost' => $preparedItem['unit_cost'],
                    'reference_type' => PurchaseReturnHeader::class,
                    'reference_id' => $return->id,
                    'notes' => $data['reason'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

            return $return->load('items.product', 'items.purchaseItem', 'items.inventoryItem', 'supplier');
        }));

        app(AuditLogService::class)->record(
            module: 'purchase_returns',
            action: 'purchase_return_created',
            auditable: $return,
            metadata: [
                'return_number' => $return->return_number,
                'purchase_reference_number' => $return->purchaseHeader?->purchaseHeader_number,
                'purchase_header_id' => $return->purchase_header_id,
                'supplier_id' => $return->supplier_id,
                'total_refund_amount' => (float) $return->total_refund_amount,
                'reason' => $return->reason,
                'items_count' => $return->items->count(),
            ],
            severity: 'critical',
        );

        app(AuditLogService::class)->record(
            module: 'inventory',
            action: 'stock_adjusted',
            auditable: $return,
            metadata: [
                'reason' => 'purchase_return_created',
                'reference_number' => $return->return_number,
                'movement' => 'out',
                'items' => $return->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                ])->values()->all(),
            ],
            severity: 'critical',
        );

        return $return;
    }

    private function generateReturnNumber(): string
    {
        return 'PR-'.date('YmdHis').str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
