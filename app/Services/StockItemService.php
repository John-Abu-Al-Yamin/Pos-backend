<?php

namespace App\Services;

use App\Models\PurchaseItem;
use App\Models\StockItem;

class StockItemService
{
    public function __construct(
        private readonly SerialNumberService $serialNumberService
    ) {}

    public function createFromPurchaseItem(PurchaseItem $purchaseItem): int
    {
        $product = $purchaseItem->product;
        $records = [];
        $serialNumbers = [];
        $now = now();

        for ($i = 0; $i < $purchaseItem->quantity; $i++) {
            $record = [
                'product_id' => $purchaseItem->product_id,
                'purchase_item_id' => $purchaseItem->id,
                'cost_price' => $purchaseItem->unit_cost,
                'condition' => $purchaseItem->condition,
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

        return $purchaseItem->quantity;
    }
}
