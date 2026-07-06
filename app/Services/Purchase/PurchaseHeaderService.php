<?php

namespace App\Services\Purchase;

use App\Models\PurchaseHeader;

class PurchaseHeaderService
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
        return PurchaseHeader::create([
            'supplier_id' => $data['supplier_id'],
            'status' => 'draft',
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

        return 'PO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }


    public function updateDraft(PurchaseHeader $purchase, array $data): PurchaseHeader
    {
        if (!$purchase->isDraft()) {
            throw new \Exception('لا يمكن تعديل فاتورة مكتملة أو ملغاة.');
        }

        $purchase->update([
            'supplier_id' => $data['supplier_id'],
            'notes' => $data['notes'] ?? null,
            'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
        ]);

        return $purchase->fresh();
    }


    public function complete(PurchaseHeader $purchase)
    {
        $purchase->update(['status' => 'completed', 'completed_at' => now()]);
        /**
         * NOTE:
         * Items logic is not implemented yet.
         * Expected behavior:
         * - Iterate over purchase items
         * - Increase product stock
         * - Create stock movement (recommended)
         */
        
    }
    public function cancel(PurchaseHeader $purchase)
    {
        $purchase->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
