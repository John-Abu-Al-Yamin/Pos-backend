<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Returns;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockItem;
use App\Events\ReturnProcessed;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function createReturn(array $data, int $userId): Returns
    {
        return DB::transaction(function () use ($data, $userId) {
            $sale = Sale::lockForUpdate()->findOrFail($data['sale_id']);

            $previousReturns = Returns::where('sale_id', $sale->id)->lockForUpdate()->get();
            $alreadyRefundedGross = (float) $previousReturns->sum(fn (Returns $r) => $r->refund_total + $r->restocking_fee);
            $requestedRefundAmount = collect($data['items'])->sum(fn ($item) => (float) $item['refund_amount']);
            $remainingRefundable = (float) $sale->total - $alreadyRefundedGross;

            if ($requestedRefundAmount > $remainingRefundable) {
                throw new \RuntimeException(
                    "المبلغ المطلوب استرداده ({$requestedRefundAmount} ج.م) يتجاوز المبلغ المتبقي القابل للاسترداد ({$remainingRefundable} ج.م). إجمالي الفاتورة: {$sale->total} ج.م، تم استرداده سابقاً: {$alreadyRefundedGross} ج.م."
                );
            }

            $return = Returns::create([
                'sale_id' => $sale->id,
                'customer_id' => $data['customer_id'] ?? $sale->customer_id,
                'user_id' => $userId,
                'return_date' => now()->format('Y-m-d'),
                'refund_method' => $data['refund_method'],
                'refund_total' => 0,
                'restocking_fee' => (float) ($data['restocking_fee'] ?? 0),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalRefund = 0;

            foreach ($data['items'] as $itemData) {
                $saleItem = SaleItem::lockForUpdate()->findOrFail($itemData['sale_item_id']);
                $product = Product::findOrFail($saleItem->product_id);
                $quantity = (int) $itemData['quantity'];
                $refundAmount = (float) $itemData['refund_amount'];
                $restock = (bool) ($itemData['restock'] ?? true);
                $conditionAfter = $itemData['condition_after_inspection'] ?? null;

                $totalRefund += $refundAmount;

                if ($product->is_serialized) {
                    $stockItemId = $itemData['stock_item_id'] ?? null;
                    if (!$stockItemId) {
                        throw new \RuntimeException("الجهاز {$product->name} يتطلب معرف المخزون");
                    }

                    $stockItem = StockItem::lockForUpdate()->findOrFail($stockItemId);

                    if ($stockItem->status !== 'sold') {
                        throw new \RuntimeException("الجهاز #{$stockItem->id} حالته {$stockItem->status} وليس مباعًا");
                    }

                    ReturnItem::create([
                        'return_id' => $return->id,
                        'sale_item_id' => $saleItem->id,
                        'stock_item_id' => $stockItem->id,
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'refund_amount' => $refundAmount,
                        'condition_after_inspection' => $conditionAfter,
                        'restock' => $restock,
                        'reason' => $itemData['reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    if ($restock) {
                        $updateData = ['status' => 'available'];
                        if ($conditionAfter) {
                            $updateData['condition'] = $conditionAfter;
                        }
                        $stockItem->update($updateData);
                    } else {
                        $stockItem->update([
                            'status' => 'damaged',
                            'condition' => $conditionAfter ?? $stockItem->condition,
                        ]);
                    }
                } else {
                    $availableStockIds = $saleItem->stockItems()
                        ->wherePivot('sale_item_id', $saleItem->id)
                        ->where('status', 'sold')
                        ->orderBy('stock_items.id')
                        ->take($quantity)
                        ->lockForUpdate()
                        ->pluck('stock_items.id');

                    if ($availableStockIds->count() < $quantity) {
                        throw new \RuntimeException(
                            "الكمية المتاحة للإرجاع من {$product->name} غير كافية. المطلوب: {$quantity}, المتاح: {$availableStockIds->count()}"
                        );
                    }

                    ReturnItem::create([
                        'return_id' => $return->id,
                        'sale_item_id' => $saleItem->id,
                        'stock_item_id' => null,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'refund_amount' => $refundAmount,
                        'condition_after_inspection' => $conditionAfter,
                        'restock' => $restock,
                        'reason' => $itemData['reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    StockItem::whereIn('id', $availableStockIds)
                        ->update(['status' => $restock ? 'available' : 'damaged']);
                }
            }

            $return->update([
                'refund_total' => $totalRefund - $return->restocking_fee,
            ]);

            $return->load([
                'sale.customer',
                'sale.saleItems.product',
                'returnItems.stockItem',
                'returnItems.product',
                'returnItems.saleItem',
                'user',
                'customer',
            ]);

            event(new ReturnProcessed($return));

            return $return;
        });
    }
}
