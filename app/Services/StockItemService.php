<?php

namespace App\Services;

use App\Models\PurchaseItem;
use App\Models\StockItem;

class StockItemService
{
    public function __construct(
        private readonly SerialNumberService $serialNumberService
    ) {}

    public function createFromPurchaseItem(PurchaseItem $purchaseItem, array $deviceDetails = []): int
    {
        $product = $purchaseItem->product;
        $records = [];
        $serialNumbers = [];
        $now = now();
        $hasDeviceDetails = !empty($deviceDetails);

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

                if ($hasDeviceDetails) {
                    $detail = $deviceDetails[$i] ?? [];
                    $record['battery_health'] = $detail['battery_health'] ?? null;
                    $record['screen_condition'] = $detail['screen_condition'] ?? null;
                    $record['body_condition'] = $detail['body_condition'] ?? null;
                    $record['face_id_working'] = $detail['face_id_working'] ?? null;
                    $record['fingerprint_working'] = $detail['fingerprint_working'] ?? null;
                    $record['camera_working'] = $detail['camera_working'] ?? null;
                    $record['speaker_working'] = $detail['speaker_working'] ?? null;
                    $record['accessories'] = $detail['accessories'] ?? null;
                    $record['notes'] = $detail['notes'] ?? null;
                }
            }

            $records[] = $record;
        }

        StockItem::insert($records);

        return $purchaseItem->quantity;
    }
}
