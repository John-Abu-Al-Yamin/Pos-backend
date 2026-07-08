<?php

namespace App\Services\PurchaseUsed;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\UsedDevicePurchaseHeader;
use Illuminate\Support\Facades\DB;

class PurchaseUsedDeviceService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

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
        return 'PO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }


    public function updateDraft(UsedDevicePurchaseHeader $purchase, array $data)
    {
        if (!$purchase->isDraft()) {
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
        if (!$purchase->isDraft()) {
            throw new \DomainException('Only draft purchases can be cancelled.');
        }

        $purchase->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }


    public function complete(UsedDevicePurchaseHeader $purchase): UsedDevicePurchaseHeader
    {
        return DB::transaction(function () use ($purchase) {

            $purchase = UsedDevicePurchaseHeader::with('items.product')
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (!$purchase->isDraft()) {
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
                $inventoryItem = InventoryItem::create([
                    'product_id' => $item->product_id,
                    'internal_serial' => $this->generateInventorySerial(),
                    'status' => 'available',
                    'cost_price' => $item->unit_price,

                    'battery_health' => $item->battery_health,
                    'screen_condition' => $item->screen_condition,
                    'body_condition' => $item->body_condition,
                    'fingerprint_working' => $item->fingerprint_working,
                    'face_id_working' => $item->face_id_working,

                    'notes' => $item->notes,
                ]);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'inventory_item_id' => $inventoryItem->id,
                    'movement_type' => 'used_purchase',
                    'movement' => 'in',
                    'quantity' => 1,
                    'unit_cost' => $item->unit_price,
                    'reference_type' => UsedDevicePurchaseHeader::class,
                    'reference_id' => $purchase->id,
                    'created_by' => auth()->id(),
                ]);
            }

            $purchase->update([
                'status' => 'completed',
                'completed_at' => $now,
            ]);

            return $purchase->fresh();
        });
    }

    private function generateInventorySerial(): string
    {
        $lastItem = InventoryItem::latest('id')->first();

        $nextNumber = $lastItem ? $lastItem->id + 1 : 1;

        return 'INV-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
