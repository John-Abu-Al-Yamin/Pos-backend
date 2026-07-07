<?php

namespace App\Services\Purchase;

use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\PurchaseHeader;
use Illuminate\Support\Facades\DB;

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



    public function complete(PurchaseHeader $purchase): PurchaseHeader
    {
        return DB::transaction(function () use ($purchase) {

            $purchase = PurchaseHeader::with('items.product')
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (!$purchase->isDraft()) {
                throw new \Exception('Only draft purchases can be completed.');
            }

            if ($purchase->items->isEmpty()) {
                throw new \Exception('Cannot complete purchase without items.');
            }

            $inventoryItemsToInsert = [];
            $now = now();

            foreach ($purchase->items as $item) {
                $product = $item->product;

                if ($product->type === 'mobile') {
                    for ($i = 0; $i < $item->quantity; $i++) {
                        $inventoryItemsToInsert[] = [
                            'product_id' => $product->id,
                            'internal_serial' => $this->generateInventorySerial(),
                            'item_condition' => 'new',
                            'status' => 'available',
                            'cost_price' => $item->unit_price,
                            'battery_health' => null,
                            'screen_condition' => null,
                            'body_condition' => null,
                            'fingerprint_working' => null,
                            'face_id_working' => null,
                            'notes' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (in_array($product->type, ['accessory', 'spare_part'])) {
                    $inventory = InventoryQuantity::firstOrCreate(
                        ['product_id' => $product->id],
                        ['quantity' => 0]
                    );

                    $inventory->increment('quantity', $item->quantity);
                }
            }

            if (!empty($inventoryItemsToInsert)) {
                InventoryItem::insert($inventoryItemsToInsert);
            }

            $purchase->update([
                'status' => 'completed',
                'completed_at' => $now,
            ]);

            return $purchase->fresh();
        });
    }

    public function cancel(PurchaseHeader $purchase)
    {
        $purchase->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    private function generateInventorySerial(): string
    {
        $lastItem = InventoryItem::latest('id')->first();

        $nextNumber = $lastItem ? $lastItem->id + 1 : 1;

        return 'INV-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
