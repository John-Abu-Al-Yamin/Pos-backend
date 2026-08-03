<?php

namespace App\Services\PurchaseUsed;

use App\Models\InventoryItem;
use App\Models\UsedDevicePurchaseHeader;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\InventoryReceivingService;
use Illuminate\Support\Facades\DB;

class PurchaseUsedDeviceService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly InventoryReceivingService $inventoryReceivingService) {}

    public function createDraft(array $data)
    {
        return UsedDevicePurchaseHeader::create([
            'purchase_number' => $this->generatePurchaseNumber(),
            'customer_id' => $data['customer_id'] ?? null,
            'status' => 'draft',
            'total_amount' => 0,
            'created_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function generatePurchaseNumber(): string
    {
        $lastPurchase = UsedDevicePurchaseHeader::latest('id')->first();
        $nextNumber = $lastPurchase ? $lastPurchase->id + 1 : 1;

        return 'PO-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function updateDraft(UsedDevicePurchaseHeader $purchase, array $data)
    {
        if (! $purchase->isDraft()) {
            throw new \DomainException('لا يمكن تعديل فاتورة مكتملة أو ملغاة.');
        }

        $purchase->update([
            'customer_id' => $data['customer_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $purchase->fresh();
    }

    public function cancel(UsedDevicePurchaseHeader $purchase): void
    {
        if (! $purchase->isDraft()) {
            throw new \DomainException('Only draft purchases can be cancelled.');
        }

        AuditLogService::withoutModelEvents(fn () => $purchase->update(['status' => 'cancelled', 'cancelled_at' => now()]));

        app(AuditLogService::class)->record(
            module: 'purchases',
            action: 'purchase_cancelled',
            auditable: $purchase->fresh(),
            metadata: [
                'reference_number' => $purchase->purchase_number,
                'customer_id' => $purchase->customer_id,
                'total_amount' => (float) $purchase->total_amount,
                'reason' => $purchase->notes,
                'purchase_type' => 'used_device',
            ],
            severity: 'warning',
        );
    }

    public function complete(UsedDevicePurchaseHeader $purchase): UsedDevicePurchaseHeader
    {
        $completedPurchase = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($purchase) {

            $purchase = UsedDevicePurchaseHeader::with('items.product')
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (! $purchase->isDraft()) {
                throw new \DomainException('Only draft purchases can be completed.');
            }

            if ($purchase->items->isEmpty()) {
                throw new \DomainException('Cannot complete purchase without items.');
            }

            foreach ($purchase->items as $item) {
                if ($item->product->type !== 'mobile') {
                    throw new \DomainException(
                        'Used device purchase can only contain mobile products.'
                    );
                }
            }

            $now = now();

            foreach ($purchase->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $this->inventoryReceivingService->receiveSerializedProduct(
                        product: $item->product,
                        internalSerial: $this->generateInventorySerial(),
                        unitCost: (float) $item->unit_price,
                        movementType: 'used_purchase',
                        referenceType: UsedDevicePurchaseHeader::class,
                        referenceId: $purchase->id,
                        itemAttributes: [
                            'source' => 'used_purchase',
                            'battery_health' => $item->battery_health,
                            'screen_condition' => $item->screen_condition,
                            'body_condition' => $item->body_condition,
                            'fingerprint_working' => $item->fingerprint_working,
                            'face_id_working' => $item->face_id_working,
                        ],
                        notes: $item->notes,
                        createdBy: auth()->id()
                    );
                }
            }

            $purchase->update([
                'status' => 'completed',
                'completed_at' => $now,
            ]);

            return $purchase->fresh();
        }));

        app(AuditLogService::class)->record(
            module: 'purchases',
            action: 'purchase_completed',
            auditable: $completedPurchase,
            metadata: [
                'reference_number' => $completedPurchase->purchase_number,
                'customer_id' => $completedPurchase->customer_id,
                'total_amount' => (float) $completedPurchase->total_amount,
                'purchase_type' => 'used_device',
            ],
            severity: 'critical',
        );

        app(AuditLogService::class)->record(
            module: 'inventory',
            action: 'stock_adjusted',
            auditable: $completedPurchase,
            metadata: [
                'reason' => 'used_device_purchase_completed',
                'reference_number' => $completedPurchase->purchase_number,
                'movement' => 'in',
                'items' => $completedPurchase->items()->get(['product_id', 'quantity', 'unit_price'])->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_price,
                ])->values()->all(),
            ],
            severity: 'critical',
        );

        return $completedPurchase;
    }

    public function deleteDraft(UsedDevicePurchaseHeader $purchase): void
    {
        if (! $purchase->isDraft()) {
            throw new \DomainException('Only draft purchases can be deleted.');
        }

        $purchase = UsedDevicePurchaseHeader::with(['customer', 'createdBy', 'usedDevicePurchaseItems.product'])->findOrFail($purchase->id);
        $snapshot = [
            'purchase' => $purchase->toArray(),
            'items' => $purchase->usedDevicePurchaseItems->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'serial_number' => $item->serial_number,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'battery_health' => $item->battery_health,
                'screen_condition' => $item->screen_condition,
                'body_condition' => $item->body_condition,
            ])->values()->all(),
        ];

        AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($purchase) {
            $purchase->usedDevicePurchaseItems()->delete();
            $purchase->delete();
        }));

        app(AuditLogService::class)->record(
            module: 'purchases',
            action: 'used_device_purchase_deleted',
            auditable: $purchase,
            oldValues: $snapshot,
            metadata: [
                'reference_number' => $purchase->purchase_number,
                'customer_id' => $purchase->customer_id,
                'total_amount' => (float) $purchase->total_amount,
                'items_count' => count($snapshot['items']),
                'purchase_type' => 'used_device',
                'snapshot' => $snapshot,
            ],
            severity: 'warning',
        );
    }

    private function generateInventorySerial(): string
    {
        $lastItem = InventoryItem::latest('id')->first();

        $nextNumber = $lastItem ? $lastItem->id + 1 : 1;

        return 'INV-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
