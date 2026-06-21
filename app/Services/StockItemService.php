<?php

namespace App\Services;

use App\Models\PurchaseItem;
use App\Models\StockItem;

class StockItemService
{
    public function __construct(
        private readonly SerialNumberService $serialNumberService
    ) {}

    public function createFromPurchaseItem(PurchaseItem $purchaseItem): array
    {
        $product = $purchaseItem->product;
        $stockItems = [];

        for ($i = 0; $i < $purchaseItem->quantity; $i++) {
            $data = [
                'product_id' => $purchaseItem->product_id,
                'purchase_item_id' => $purchaseItem->id,
                'cost_price' => $purchaseItem->unit_cost,
                'condition' => $purchaseItem->condition,
                'status' => 'available',
            ];

            if ($product->is_serialized) {
                $data['serial_number'] = $this->serialNumberService->generate($product);
            }

            $stockItems[] = StockItem::create($data);
        }

        return $stockItems;
    }
}
