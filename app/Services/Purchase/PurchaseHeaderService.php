<?php

namespace App\Services\Purchase;

use App\Models\InventoryItem;
use App\Models\PurchaseHeader;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\InventoryReceivingService;
use Illuminate\Support\Facades\DB;

class PurchaseHeaderService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly InventoryReceivingService $inventoryReceivingService) {}

    public function createDraft(array $data)
    {
        return PurchaseHeader::create([
            'supplier_id' => $data['supplier_id'],
            'created_by' => auth()->id(),
            'total_amount' => 0,
            'notes' => $data['notes'] ?? null,
            'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
            'purchaseHeader_number' => $this->generatePurchaseNumber(),

        ]);
    }

    private function generatePurchaseNumber(): string
    {
        $lastPurchase = PurchaseHeader::latest('id')->first();

        $nextNumber = $lastPurchase ? $lastPurchase->id + 1 : 1;

        return 'PO-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function updateDraft(PurchaseHeader $purchase, array $data): PurchaseHeader
    {
        if (! $purchase->isDraft()) {
            throw new \DomainException('لا يمكن تعديل فاتورة مكتملة أو ملغاة.');
        }

        $purchase->update([
            'supplier_id' => $data['supplier_id'],
            'notes' => $data['notes'] ?? null,
            'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
        ]);

        return $purchase->fresh();
    }

    public function complete(PurchaseHeader $purchase): PurchaseHeader
    {
        $completedPurchase = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($purchase) {

            $purchase = PurchaseHeader::with('items.product')
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (! $purchase->isDraft()) {
                throw new \DomainException('Only draft purchases can be completed.');
            }

            if ($purchase->items->isEmpty()) {
                throw new \DomainException('Cannot complete purchase without items.');
            }

            $now = now();

            foreach ($purchase->items as $item) {
                $product = $item->product;

                if ($product->type === 'mobile') {
                    for ($i = 0; $i < $item->quantity; $i++) {
                        $this->inventoryReceivingService->receiveSerializedProduct(
                            product: $product,
                            internalSerial: $this->generateInventorySerial(),
                            unitCost: (float) $item->unit_price,
                            movementType: 'purchase',
                            referenceType: PurchaseHeader::class,
                            referenceId: $purchase->id,
                            itemAttributes: [
                                'source' => 'new_purchase',
                                'battery_health' => null,
                                'screen_condition' => null,
                                'body_condition' => null,
                                'fingerprint_working' => null,
                                'face_id_working' => null,
                            ],
                            createdBy: auth()->id()
                        );
                    }
                }

                if (in_array($product->type, ['accessory', 'spare_part'])) {
                    $this->inventoryReceivingService->receiveQuantityProduct(
                        product: $product,
                        quantity: (int) $item->quantity,
                        unitCost: (float) $item->unit_price,
                        movementType: 'purchase',
                        referenceType: PurchaseHeader::class,
                        referenceId: $purchase->id,
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
                'reference_number' => $completedPurchase->purchaseHeader_number,
                'supplier_invoice_number' => $completedPurchase->supplier_invoice_number,
                'supplier_id' => $completedPurchase->supplier_id,
                'total_amount' => (float) $completedPurchase->total_amount,
                'completed_at' => $completedPurchase->completed_at,
            ],
            severity: 'critical',
        );

        app(AuditLogService::class)->record(
            module: 'inventory',
            action: 'stock_adjusted',
            auditable: $completedPurchase,
            metadata: [
                'reason' => 'purchase_completed',
                'reference_number' => $completedPurchase->purchaseHeader_number,
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

    public function cancel(PurchaseHeader $purchase): void
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
                'reference_number' => $purchase->purchaseHeader_number,
                'supplier_invoice_number' => $purchase->supplier_invoice_number,
                'supplier_id' => $purchase->supplier_id,
                'total_amount' => (float) $purchase->total_amount,
                'reason' => $purchase->notes,
            ],
            severity: 'warning',
        );
    }

    public function deleteDraft(PurchaseHeader $purchase): void
    {
        if (! $purchase->isDraft()) {
            throw new \DomainException('Only draft purchases can be deleted.');
        }

        $purchase = PurchaseHeader::with(['supplier', 'createdBy', 'items.product'])->findOrFail($purchase->id);
        $snapshot = [
            'purchase' => $purchase->toArray(),
            'items' => $purchase->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
            ])->values()->all(),
        ];

        AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($purchase) {
            $purchase->items()->delete();
            $purchase->delete();
        }));

        app(AuditLogService::class)->record(
            module: 'purchases',
            action: 'purchase_deleted',
            auditable: $purchase,
            oldValues: $snapshot,
            metadata: [
                'reference_number' => $purchase->purchaseHeader_number,
                'supplier_invoice_number' => $purchase->supplier_invoice_number,
                'supplier_id' => $purchase->supplier_id,
                'total_amount' => (float) $purchase->total_amount,
                'items_count' => count($snapshot['items']),
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
