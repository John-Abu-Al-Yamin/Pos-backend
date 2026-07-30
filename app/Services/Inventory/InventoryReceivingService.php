<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\Product;
use App\Models\StockMovement;

class InventoryReceivingService
{
    public function receiveQuantityProduct(
        Product $product,
        int $quantity,
        float $unitCost,
        string $movementType,
        string $referenceType,
        int $referenceId,
        ?string $notes = null,
        ?int $createdBy = null
    ): StockMovement {
        $inventory = InventoryQuantity::where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            $inventory = InventoryQuantity::create([
                'product_id' => $product->id,
                'quantity' => 0,
                'cost_price' => 0,
            ]);
        }

        $currentQty = $inventory->quantity;
        $currentCost = $inventory->cost_price;

        $weightedAvgCost = $currentQty + $quantity > 0
            ? (($currentQty * $currentCost) + ($quantity * $unitCost)) / ($currentQty + $quantity)
            : $unitCost;

        $inventory->increment('quantity', $quantity);
        $inventory->update(['cost_price' => round($weightedAvgCost, 2)]);

        return StockMovement::create([
            'product_id' => $product->id,
            'inventory_item_id' => null,
            'movement_type' => $movementType,
            'movement' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }

    public function receiveSerializedProduct(
        Product $product,
        string $internalSerial,
        float $unitCost,
        string $movementType,
        string $referenceType,
        int $referenceId,
        array $itemAttributes = [],
        ?string $notes = null,
        ?int $createdBy = null
    ): array {
        $inventoryItem = InventoryItem::create(array_merge([
            'product_id' => $product->id,
            'internal_serial' => $internalSerial,
            'status' => 'available',
            'source' => null,
            'cost_price' => $unitCost,
            'battery_health' => null,
            'screen_condition' => null,
            'body_condition' => null,
            'fingerprint_working' => null,
            'face_id_working' => null,
            'notes' => $notes,
        ], $itemAttributes));

        $stockMovement = StockMovement::create([
            'product_id' => $product->id,
            'inventory_item_id' => $inventoryItem->id,
            'movement_type' => $movementType,
            'movement' => 'in',
            'quantity' => 1,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy ?? auth()->id(),
        ]);

        return [$inventoryItem, $stockMovement];
    }
}
