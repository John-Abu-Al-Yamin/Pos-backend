<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SparePartSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(['name' => 'Spare Parts']);

        $supplier = Supplier::where('name', 'PhoneParts Direct')->first();

        $parts = [
            ['name' => 'iPhone 15 Pro Max Screen Assembly', 'cost' => 120.00, 'qty' => 3],
            ['name' => 'iPhone 14 Battery', 'cost' => 35.00, 'qty' => 5],
            ['name' => 'Galaxy S24 Ultra Screen', 'cost' => 95.00, 'qty' => 2],
            ['name' => 'USB-C Charging Port Flex', 'cost' => 12.00, 'qty' => 10],
            ['name' => 'iPhone 15 Pro Max Back Glass', 'cost' => 45.00, 'qty' => 3],
            ['name' => 'Galaxy A55 LCD Display', 'cost' => 55.00, 'qty' => 2],
            ['name' => 'Taptic Engine (iPhone 15)', 'cost' => 18.00, 'qty' => 4],
            ['name' => 'Speaker Module (Generic)', 'cost' => 8.00, 'qty' => 8],
            ['name' => 'Pixel 8 Pro Battery', 'cost' => 30.00, 'qty' => 2],
            ['name' => 'Samsung Galaxy Tab A9 Screen', 'cost' => 65.00, 'qty' => 1],
            ['name' => 'iPhone 15 Pro Max Camera Module', 'cost' => 85.00, 'qty' => 2],
            ['name' => 'Side Button Flex Cable (iPhone)', 'cost' => 6.00, 'qty' => 10],
            ['name' => 'Pixel 7a Screen Assembly', 'cost' => 70.00, 'qty' => 2],
            ['name' => 'iPhone SE 3rd Gen Battery', 'cost' => 28.00, 'qty' => 3],
            ['name' => 'Galaxy Z Flip 6 Hinge Assembly', 'cost' => 150.00, 'qty' => 1],
        ];

        $products = [];
        foreach ($parts as $part) {
            $products[] = Product::create([
                'name' => $part['name'],
                'category_id' => $category->id,
                'product_category' => 'part',
                'is_serialized' => false,
            ]);
        }

        $purchase = PurchaseHeader::create([
            'supplier_id' => $supplier->id,
            'date' => '2026-06-20',
            'type' => 'purchase',
            'reference' => 'Spare parts initial stock',
        ]);

        foreach ($parts as $i => $part) {
            $product = $products[$i];
            $lineTotal = round($part['qty'] * $part['cost'], 2);

            $purchaseItem = PurchaseItem::create([
                'purchase_header_id' => $purchase->id,
                'product_id' => $product->id,
                'quantity' => $part['qty'],
                'unit_cost' => $part['cost'],
                'line_total' => $lineTotal,
                'condition' => 'new',
            ]);

            for ($j = 0; $j < $part['qty']; $j++) {
                StockItem::create([
                    'product_id' => $product->id,
                    'purchase_item_id' => $purchaseItem->id,
                    'cost_price' => $part['cost'],
                    'condition' => 'new',
                    'status' => 'available',
                ]);
            }
        }

        $purchase->recalculateTotal();
    }
}
