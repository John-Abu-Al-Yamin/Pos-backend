<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogService;

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
        $currentCost = (float) $inventory->cost_price;

        $weightedAvgCost = $currentQty + $quantity > 0
            ? (($currentQty * $currentCost) + ($quantity * $unitCost)) / ($currentQty + $quantity)
            : $unitCost;
        $newCost = round($weightedAvgCost, 2);

        $inventory->increment('quantity', $quantity);
        $inventory->update(['cost_price' => $newCost]);

        if (round($currentCost, 2) !== $newCost) {
            app(AuditLogService::class)->record(
                module: 'inventory',
                action: 'inventory_cost_changed',
                auditable: $inventory,
                oldValues: ['cost_price' => round($currentCost, 2)],
                newValues: ['cost_price' => $newCost],
                changedFields: ['cost_price'],
                metadata: [
                    'product_id' => $product->id,
                    'old_cost' => round($currentCost, 2),
                    'new_cost' => $newCost,
                    'reason' => $movementType,
                    'related_document_type' => $referenceType,
                    'related_document_id' => $referenceId,
                ],
                severity: 'warning',
                deferUntilCommit: true,
            );
        }

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

        app(AuditLogService::class)->record(
            module: 'inventory',
            action: 'inventory_cost_changed',
            auditable: $inventoryItem,
            oldValues: ['cost_price' => null],
            newValues: ['cost_price' => round($unitCost, 2)],
            changedFields: ['cost_price'],
            metadata: [
                'product_id' => $product->id,
                'inventory_item_id' => $inventoryItem->id,
                'old_cost' => null,
                'new_cost' => round($unitCost, 2),
                'reason' => $movementType,
                'related_document_type' => $referenceType,
                'related_document_id' => $referenceId,
            ],
            severity: 'warning',
            deferUntilCommit: true,
        );

        return [$inventoryItem, $stockMovement];
    }
}
