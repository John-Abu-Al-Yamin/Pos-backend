<?php

namespace App\Services\Sales;

use App\Models\SalesHeader;
use App\Models\SalesItem;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class SalesCheckoutService
{
    public function checkout(array $data)
    {
        return DB::transaction(function () use ($data) {

            $subtotal = 0;
            $preparedItems = [];

            foreach ($data['items'] as $item) {

                // Mobile
                if (isset($item['inventory_item_id'])) {
                    $inventoryItem = InventoryItem::with('product')
                        ->where('id', $item['inventory_item_id'])
                        ->where('status', 'available')
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($inventoryItem->product->type !== 'mobile') {
                        throw new \RuntimeException('Inventory item must be a mobile product.');
                    }

                    $quantity = $item['quantity'] ?? 1;
                    $unitPrice = $item['unit_price'];
                    $totalPrice = $unitPrice;

                    $preparedItems[] = [
                        'product_id' => $inventoryItem->product_id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'type' => 'mobile',
                        'inventory_item' => $inventoryItem,
                    ];

                    $subtotal += $totalPrice;
                }

                // Accessory / Spare Part
                else {
                    $product = Product::findOrFail($item['product_id']);

                    if (! in_array($product->type, ['accessory', 'spare_part'])) {
                        throw new \RuntimeException('Product must be accessory or spare part.');
                    }

                    $quantity = $item['quantity'];
                    $unitPrice = $item['unit_price'];
                    $totalPrice = $quantity * $unitPrice;

                    $inventoryQuantity = InventoryQuantity::where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventoryQuantity) {
                        throw new \RuntimeException('Product not found in inventory: ' . $product->name);
                    }

                    $preparedItems[] = [
                        'product_id' => $product->id,
                        'inventory_item_id' => null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'type' => 'quantity_product',
                        'inventory_quantity' => $inventoryQuantity,
                    ];

                    $subtotal += $totalPrice;
                }
            }

            $discount = $data['discount_amount'] ?? 0;
            $total = $subtotal - $discount;

            if ($total < 0) {
                throw new \RuntimeException('Discount cannot be greater than subtotal.');
            }

            $sale = SalesHeader::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id' => $data['customer_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($preparedItems as $preparedItem) {

                $saleItem = SalesItem::create([
                    'sales_header_id' => $sale->id,
                    'product_id' => $preparedItem['product_id'],
                    'inventory_item_id' => $preparedItem['inventory_item_id'],
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $preparedItem['unit_price'],
                    'total_price' => $preparedItem['total_price'],
                ]);

                if ($preparedItem['type'] === 'mobile') {
                    $preparedItem['inventory_item']->update([
                        'status' => 'sold',
                    ]);
                } else {
                    $updated = DB::table('inventory_quantities')
                        ->where('id', $preparedItem['inventory_quantity']->id)
                        ->where('quantity', '>=', $preparedItem['quantity'])
                        ->decrement('quantity', $preparedItem['quantity']);

                    if ($updated === 0) {
                        throw new \RuntimeException('Not enough stock for product.');
                    }
                }

                StockMovement::create([
                    'product_id' => $preparedItem['product_id'],
                    'inventory_item_id' => $preparedItem['inventory_item_id'],
                    'movement_type' => 'sale',
                    'movement' => 'out',
                    'quantity' => $preparedItem['quantity'],
                    'reference_type' => SalesHeader::class,
                    'reference_id' => $sale->id,
                    'created_by' => auth()->id(),
                ]);
            }

            return $sale->load('items.product', 'items.inventoryItem', 'customer');
        });
    }

    private function generateInvoiceNumber(): string
    {
        $lastSale = SalesHeader::latest('id')->first();
        $nextId = $lastSale ? $lastSale->id + 1 : 1;

        return 'SAL-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }
}
