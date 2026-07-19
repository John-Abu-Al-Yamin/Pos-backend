<?php

namespace App\Services\Sales;

use App\Models\SalesHeader;
use App\Models\SalesItem;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\StockMovement;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;

class SalesCheckoutService
{
    public function __construct(
        private PricingService $pricingService
    ) {}

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

                    $quantity = 1; // Mobiles are unique physical items, quantity is strictly 1
                    $unitPrice = $item['unit_price'];
                    $costPrice = $this->pricingService->resolveCostPrice(
                        $inventoryItem->product,
                        $inventoryItem
                    );
                    $totalPrice = $quantity * $unitPrice;

                    $this->validatePrice($unitPrice, $costPrice);

                    $preparedItems[] = [
                        'product_id' => $inventoryItem->product_id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $costPrice,
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

                    $inventoryQuantity = InventoryQuantity::where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventoryQuantity) {
                        throw new \RuntimeException('Product not found in inventory: ' . $product->name);
                    }

                    $costPrice = $this->pricingService->resolveCostPrice($product);
                    $totalPrice = $quantity * $unitPrice;

                    $this->validatePrice($unitPrice, $costPrice);

                    $preparedItems[] = [
                        'product_id' => $product->id,
                        'inventory_item_id' => null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $costPrice,
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

                SalesItem::create([
                    'sales_header_id' => $sale->id,
                    'product_id' => $preparedItem['product_id'],
                    'inventory_item_id' => $preparedItem['inventory_item_id'],
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $preparedItem['unit_price'],
                    'unit_cost' => $preparedItem['unit_cost'],
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
                    'unit_cost' => $preparedItem['unit_cost'],
                    'reference_type' => SalesHeader::class,
                    'reference_id' => $sale->id,
                    'created_by' => auth()->id(),
                ]);
            }

            return $sale->load('items.product', 'items.inventoryItem', 'customer');
        });
    }

    private function validatePrice(float $unitPrice, float $costPrice): void
    {
        if ($costPrice >= 0 && $unitPrice < $costPrice) {
            throw new \RuntimeException('Selling price cannot be below cost price.');
        }
    }

    private function generateInvoiceNumber(): string
    {
        return 'SAL-' . date('YmdHis') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
