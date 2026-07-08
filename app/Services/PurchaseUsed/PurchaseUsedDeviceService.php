<?php

namespace App\Services\PurchaseUsed;

use App\Models\InventoryItem;
use App\Models\UsedDevicePurchaseHeader;

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
        if (!$purchase->isDraft()) {
            throw new \DomainException('Only draft purchases can be completed.');
        }

        if ($purchase->items->isEmpty()) {
            throw new \DomainException('Cannot complete purchase without items.');
        }

        $now = now();

        /**
         * TODO:
         * Iterate through all used device purchase items.
         *
         * For each item:
         * - Create a record in inventory_items.
         * - Set item_condition = used.
         * - Set status = available.
         * - Generate an internal serial.
         * - Copy the inspection/device information.
         * - Create a stock movement:
         *      movement_type = used_purchase
         *      movement = in
         *      quantity = 1
         *
         * Inventory is updated only when the purchase is completed.
         */
        $purchase->update([
            'status' => 'completed',
            'completed_at' => $now,
        ]);

        return $purchase->fresh();
    }


    private function generateInventorySerial(): string
    {
        $lastItem = InventoryItem::latest('id')->first();

        $nextNumber = $lastItem ? $lastItem->id + 1 : 1;

        return 'INV-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
