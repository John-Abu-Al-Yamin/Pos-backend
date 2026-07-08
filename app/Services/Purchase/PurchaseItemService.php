<?php

namespace App\Services\Purchase;

use App\Models\Product;
use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;

class PurchaseItemService
{
    public function __construct()
    {
        //
    }

    public function addItem(array $data): PurchaseItem
    {
        $purchase = PurchaseHeader::findOrFail($data['purchase_header_id']);

        if (!$purchase->isDraft()) {
            throw new \DomainException('Cannot modify a completed purchase.');
        }

        $product = Product::findOrFail($data['product_id']);
        if ($product->type === 'mobile' && (float) $data['quantity'] != (int) $data['quantity']) {
            throw new \DomainException('Mobile quantity must be a whole number.');
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
            throw new \DomainException('Cannot modify a completed purchase.');
        }

        $product = Product::findOrFail($data['product_id']);
        if ($product->type === 'mobile' && (float) $data['quantity'] != (int) $data['quantity']) {
            throw new \DomainException('Mobile quantity must be a whole number.');
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
            throw new \DomainException('Cannot modify a completed purchase.');
        }

        $purchase = $item->purchaseHeader;

        $item->delete();

        $this->recalculateTotal($purchase);
    }

    private function recalculateTotal(PurchaseHeader $purchase): void
    {
        $total = $purchase->items()->sum('total_price');

        $purchase->update([
            'total_amount' => $total,
        ]);
    }
}
