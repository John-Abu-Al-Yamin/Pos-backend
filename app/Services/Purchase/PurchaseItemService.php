<?php

namespace App\Services\Purchase;

use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;

class PurchaseItemService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function addItem(PurchaseHeader $purchase, array $data): PurchaseItem
    {
        if (!$purchase->isDraft()) {
            throw new \Exception('Cannot modify a completed purchase.');
        }

        $item = $purchase->items()->create([
            'product_id' => $data['product_id'],
            'quantity'   => $data['quantity'],
            'unit_price'  => $data['unit_cost'],
            'total_price' => $data['quantity'] * $data['unit_cost'],
        ]);

        $this->recalculateTotal($purchase);

        return $item;
    }

    public function updateItem(PurchaseItem $item, array $data): PurchaseItem
    {
        if (!$item->purchaseHeader->isDraft()) {
            throw new Exception('Cannot modify a completed purchase.');
        }

        $item->update([
            'product_id' => $data['product_id'],
            'quantity'   => $data['quantity'],
            'unit_price'  => $data['unit_cost'],
            'total_price' => $data['quantity'] * $data['unit_cost'],
        ]);

        $this->recalculateTotal($item->purchaseHeader);

        return $item->fresh();
    }

    public function deleteItem(PurchaseItem $item): void
    {
        if (!$item->purchaseHeader->isDraft()) {
            throw new \Exception('Cannot modify a completed purchase.');
        }

        $purchase = $item->purchaseHeader;

        $item->delete();

        $this->recalculateTotal($purchase);
    }

    private function recalculateTotal(PurchaseHeader $purchase): void
    {
        $total = $purchase->items()->sum('line_total');

        $purchase->update([
            'total_amount' => $total,
        ]);
    }
}
