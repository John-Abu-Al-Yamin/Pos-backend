<?php

namespace App\Services\Purchase;

use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\PurchaseHeader;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        return DB::transaction(function () use ($purchase) {

            $purchase = PurchaseHeader::with('items.product')
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (!$purchase->isDraft()) {
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

                        $inventoryItem = InventoryItem::create([
                            'product_id' => $product->id,
                            'internal_serial' => $this->generateInventorySerial(),
                            'status' => 'available',
                            'cost_price' => $item->unit_price,
                            'battery_health' => null,
                            'screen_condition' => null,
                            'body_condition' => null,
                            'fingerprint_working' => null,
                            'face_id_working' => null,
                            'notes' => null,
                        ]);

                        // Create stock movement for the mobile item
                        StockMovement::create([
                            'product_id' => $product->id,
                            'inventory_item_id' => $inventoryItem->id,
                            'movement_type' => 'purchase',
                            'movement' => 'in',
                            'quantity' => 1,
                            'unit_cost' => $item->unit_price,
                            'reference_type' => PurchaseHeader::class,
                            'reference_id' => $purchase->id,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                if (in_array($product->type, ['accessory', 'spare_part'])) {
                    $inventory = InventoryQuantity::firstOrCreate(
                        ['product_id' => $product->id],
                        ['quantity' => 0]
                    );

                    $inventory->increment('quantity', $item->quantity);
                    // Create stock movement for the accessory or spare part
                    StockMovement::create([
                        'product_id' => $product->id,
                        'inventory_item_id' => null,
                        'movement_type' => 'purchase',
                        'movement' => 'in',
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->unit_price,
                        'reference_type' => PurchaseHeader::class,
                        'reference_id' => $purchase->id,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $purchase->update([
                'status' => 'completed',
                'completed_at' => $now,
            ]);

            return $purchase->fresh();
        });
    }

    public function cancel(PurchaseHeader $purchase): void
    {
        if (!$purchase->isDraft()) {
            throw new \DomainException('Only draft purchases can be cancelled.');
        }

        $purchase->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    private function generateInventorySerial(): string
    {
        $lastItem = InventoryItem::latest('id')->first();

        $nextNumber = $lastItem ? $lastItem->id + 1 : 1;

        return 'INV-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
