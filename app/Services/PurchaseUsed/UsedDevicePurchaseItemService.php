<?php

namespace App\Services\PurchaseUsed;

use App\Models\Product;
use App\Models\UsedDevicePurchaseHeader;
use App\Models\UsedDevicePurchaseItem;

class UsedDevicePurchaseItemService
{
    public function addItem(
        UsedDevicePurchaseHeader $purchase,
        array $data
    ): UsedDevicePurchaseItem {
        if (!$purchase->isDraft()) {
            throw new \DomainException('Cannot add items to a completed or cancelled purchase.');
        }

        $product = Product::findOrFail($data['product_id']);
        if ($product->type !== 'mobile') {
            throw new \DomainException('Only mobile products can be added to a used device purchase.');
        }

        $quantity = $data['quantity'] ?? 1;
        if ((float) $quantity != (int) $quantity || $quantity < 1) {
            throw new \DomainException('Quantity must be a positive whole number for used device items.');
        }

        $unitPrice = $data['unit_price'];

        $item = $purchase->items()->create([
            'product_id' => $data['product_id'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,

            'serial_number' => $this->generateSerialNumber(),
            'battery_health' => $data['battery_health'] ?? null,
            'screen_condition' => $data['screen_condition'] ?? null,
            'body_condition' => $data['body_condition'] ?? null,
            'fingerprint_working' => $data['fingerprint_working'] ?? null,
            'face_id_working' => $data['face_id_working'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->recalculateTotal($purchase);

        return $item;
    }

    public function updateItem(
        UsedDevicePurchaseItem $item,
        array $data
    ): UsedDevicePurchaseItem {
        if (!$item->purchaseHeader->isDraft()) {
            throw new \DomainException('Cannot update items in a completed or cancelled purchase.');
        }

        $productId = $data['product_id'] ?? $item->product_id;
        if (isset($data['product_id'])) {
            $product = Product::findOrFail($data['product_id']);
            if ($product->type !== 'mobile') {
                throw new \DomainException('Only mobile products can be added to a used device purchase.');
            }
        }

        $quantity = $data['quantity'] ?? $item->quantity;
        if ((float) $quantity != (int) $quantity || $quantity < 1) {
            throw new \DomainException('Quantity must be a positive whole number for used device items.');
        }

        $unitPrice = $data['unit_price'] ?? $item->unit_price;

        $item->update([
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,

            'battery_health' => $data['battery_health'] ?? $item->battery_health,
            'screen_condition' => $data['screen_condition'] ?? $item->screen_condition,
            'body_condition' => $data['body_condition'] ?? $item->body_condition,
            'fingerprint_working' => $data['fingerprint_working'] ?? $item->fingerprint_working,
            'face_id_working' => $data['face_id_working'] ?? $item->face_id_working,
            'notes' => $data['notes'] ?? $item->notes,
        ]);

        $this->recalculateTotal($item->purchaseHeader);

        return $item->fresh();
    }

    public function deleteItem(UsedDevicePurchaseItem $item): void
    {
        if (!$item->purchaseHeader->isDraft()) {
            throw new \DomainException('Cannot delete items from a completed or cancelled purchase.');
        }

        $purchase = $item->purchaseHeader;

        $item->delete();

        $this->recalculateTotal($purchase);
    }

    private function generateSerialNumber(): string
    {
        $lastItem = UsedDevicePurchaseItem::latest('id')->first();
        $nextNumber = $lastItem ? $lastItem->id + 1 : 1;
        return 'USD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function recalculateTotal(UsedDevicePurchaseHeader $purchase): void
    {
        $total = $purchase->items()->sum('total_price');

        $purchase->update([
            'total_amount' => $total,
        ]);
    }
}
