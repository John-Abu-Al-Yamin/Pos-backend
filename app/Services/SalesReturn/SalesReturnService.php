<?php

namespace App\Services\SalesReturn;

use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\SalesHeader;
use App\Models\SalesReturnHeader;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    public function processReturn(array $data)
    {
        $return = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($data) {

            $sale = SalesHeader::with('items.product', 'items.inventoryItem')
                ->lockForUpdate()
                ->findOrFail($data['sales_header_id']);

            $returnedQtyByItem = SalesReturnItem::whereHas(
                'salesReturnHeader'
            )
                ->whereIn('sales_item_id', $sale->items->pluck('id'))
                ->groupBy('sales_item_id')
                ->selectRaw('sales_item_id, SUM(quantity) as total_returned')
                ->pluck('total_returned', 'sales_item_id');

            $totalRefund = 0;
            $preparedItems = [];

            foreach ($data['items'] as $item) {

                $salesItem = $sale->items->firstWhere('id', $item['sales_item_id']);

                if (! $salesItem) {
                    throw new \RuntimeException('Sales item not found in this invoice.');
                }

                $alreadyReturned = (int) ($returnedQtyByItem->get($item['sales_item_id']) ?? 0);
                $returnableQty = $salesItem->quantity - $alreadyReturned;

                if ($item['quantity'] > $returnableQty) {
                    throw new \RuntimeException('Return quantity exceeds remaining returnable quantity.');
                }

                if ((float) $item['unit_refund_amount'] < 0) {
                    throw new \RuntimeException('Refund amount cannot be negative.');
                }

                $unitRefundAmount = $this->allowedUnitRefundAmount($sale, $salesItem);

                if (isset($item['inventory_item_id'])) {
                    $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($item['inventory_item_id']);

                    if ($inventoryItem->status !== 'sold') {
                        throw new \RuntimeException('Device is not in sold status.');
                    }

                    if ($inventoryItem->id !== $salesItem->inventory_item_id) {
                        throw new \RuntimeException('This device was not sold in this line item.');
                    }

                    $preparedItems[] = [
                        'type' => 'mobile',
                        'sales_item_id' => $salesItem->id,
                        'product_id' => $salesItem->product_id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity' => 1,
                        'unit_refund_amount' => $unitRefundAmount,
                        'total_refund' => $unitRefundAmount,
                        'unit_cost' => (float) $inventoryItem->cost_price,
                        'inventory_item' => $inventoryItem,
                    ];

                    $totalRefund += $unitRefundAmount;
                } else {
                    $product = $salesItem->product;

                    if (! in_array($product->type, ['accessory', 'spare_part'])) {
                        throw new \RuntimeException('Product must be accessory or spare part.');
                    }

                    $inventoryQuantity = InventoryQuantity::where('product_id', $product->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $preparedItems[] = [
                        'type' => 'quantity_product',
                        'sales_item_id' => $salesItem->id,
                        'product_id' => $product->id,
                        'inventory_item_id' => null,
                        'quantity' => (int) $item['quantity'],
                        'unit_refund_amount' => $unitRefundAmount,
                        'total_refund' => round((int) $item['quantity'] * $unitRefundAmount, 2),
                        'unit_cost' => (float) $salesItem->unit_cost,
                        'inventory_quantity' => $inventoryQuantity,
                    ];

                    $totalRefund += round((int) $item['quantity'] * $unitRefundAmount, 2);
                }
            }

            $return = SalesReturnHeader::create([
                'sales_header_id' => $sale->id,
                'return_number' => $this->generateReturnNumber(),
                'customer_id' => $data['customer_id'] ?? $sale->customer_id,
                'user_id' => auth()->id(),
                'total_refund_amount' => $totalRefund,
                'reason' => $data['reason'] ?? null,
                'return_date' => Carbon::parse($data['return_date'] ?? now())->toDateString(),
            ]);

            foreach ($preparedItems as $preparedItem) {

                SalesReturnItem::create([
                    'sales_return_header_id' => $return->id,
                    'sales_item_id' => $preparedItem['sales_item_id'],
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

                    $newQty = $currentQty + $returnQty;
                    $weightedAvgCost = $newQty > 0
                        ? (($currentQty * $currentCost) + ($returnQty * $returnCost)) / $newQty
                        : $returnCost;
                    $newCost = round($weightedAvgCost, 2);

                    $invQty->increment('quantity', $returnQty);
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
                                'reason' => 'sales_return_created',
                                'related_document_type' => SalesReturnHeader::class,
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
                    'movement_type' => 'sales_return',
                    'movement' => 'in',
                    'quantity' => $preparedItem['quantity'],
                    'unit_cost' => $preparedItem['unit_cost'],
                    'reference_type' => SalesReturnHeader::class,
                    'reference_id' => $return->id,
                    'notes' => $data['reason'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

            return $return->load('items.product', 'items.salesItem.inventoryItem', 'customer');
        }));

        app(AuditLogService::class)->record(
            module: 'sales_returns',
            action: 'sales_return_created',
            auditable: $return,
            metadata: [
                'return_number' => $return->return_number,
                'invoice_number' => $return->salesHeader?->invoice_number,
                'sales_header_id' => $return->sales_header_id,
                'customer_id' => $return->customer_id,
                'total_refund_amount' => (float) $return->total_refund_amount,
                'reason' => $return->reason,
                'items_count' => $return->items->count(),
            ],
            severity: 'critical',
        );

        app(AuditLogService::class)->record(
            module: 'sales_returns',
            action: 'refund_processed',
            auditable: $return,
            metadata: [
                'return_number' => $return->return_number,
                'invoice_number' => $return->salesHeader?->invoice_number,
                'amount' => (float) $return->total_refund_amount,
                'reason' => $return->reason,
            ],
            severity: 'critical',
        );

        app(AuditLogService::class)->record(
            module: 'inventory',
            action: 'stock_adjusted',
            auditable: $return,
            metadata: [
                'reason' => 'sales_return_created',
                'reference_number' => $return->return_number,
                'movement' => 'in',
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
        return 'SR-'.date('YmdHis').str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function allowedUnitRefundAmount(SalesHeader $sale, $salesItem): float
    {
        $quantity = (float) $salesItem->quantity;

        if ($quantity <= 0) {
            return 0;
        }

        $lineTotal = (float) $salesItem->total_price;
        $subtotal = (float) $sale->subtotal;
        $discount = (float) $sale->discount_amount;

        $lineDiscount = $subtotal > 0
            ? $discount * ($lineTotal / $subtotal)
            : 0;

        $lineNetAmount = max(0, $lineTotal - $lineDiscount);

        return round($lineNetAmount / $quantity, 2);
    }
}
