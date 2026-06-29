<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
    ) {}

    public function createSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $isImmediate = in_array($paymentMethod, ['cash', 'card', 'transfer']);

            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'created_by_name' => $data['created_by_name'] ?? null,
                'date' => $data['date'] ?? now()->format('Y-m-d'),
                'payment_method' => $paymentMethod,
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

            if ($isImmediate) {
                $sale->payment_received_at = now();
                $sale->saveQuietly();
                $this->ledger->recordSalePayment($sale);
            }

            $sale->load(['customer', 'saleItems.product', 'saleItems.stockItems']);

            return $sale;
        });
    }
}
