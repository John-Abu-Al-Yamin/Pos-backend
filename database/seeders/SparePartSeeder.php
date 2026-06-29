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
        $category = Category::firstOrCreate(['name' => 'قطع غيار']);

        $supplier = Supplier::where('name', 'قطع الهواتف المباشر')->first();

        $parts = [
            ['name' => 'طقم شاشة آيفون 15 برو ماكس', 'cost' => 120.00, 'qty' => 3],
            ['name' => 'بطارية آيفون 14', 'cost' => 35.00, 'qty' => 5],
            ['name' => 'شاشة جالاكسي S24 ألترا', 'cost' => 95.00, 'qty' => 2],
            ['name' => 'كابل منفذ شحن USB-C', 'cost' => 12.00, 'qty' => 10],
            ['name' => 'زجاج خلفي لآيفون 15 برو ماكس', 'cost' => 45.00, 'qty' => 3],
            ['name' => 'شاشة LCD جالاكسي A55', 'cost' => 55.00, 'qty' => 2],
            ['name' => 'محرك تابتيش (آيفون 15)', 'cost' => 18.00, 'qty' => 4],
            ['name' => 'وحدة سماعة (عام)', 'cost' => 8.00, 'qty' => 8],
            ['name' => 'بطارية بكسل 8 برو', 'cost' => 30.00, 'qty' => 2],
            ['name' => 'شاشة سامسونج جالاكسي تاب A9', 'cost' => 65.00, 'qty' => 1],
            ['name' => 'كاميرا آيفون 15 برو ماكس', 'cost' => 85.00, 'qty' => 2],
            ['name' => 'كابل أزرار جانبية (آيفون)', 'cost' => 6.00, 'qty' => 10],
            ['name' => 'طقم شاشة بكسل 7a', 'cost' => 70.00, 'qty' => 2],
            ['name' => 'بطارية آيفون SE الجيل الثالث', 'cost' => 28.00, 'qty' => 3],
            ['name' => 'مفصلة جالاكسي Z فليب 6', 'cost' => 150.00, 'qty' => 1],
        ];

        $products = [];
        foreach ($parts as $part) {
            $sellingPrice = round($part['cost'] * 1.8, 2);
            $products[] = Product::create([
                'name' => $part['name'],
                'category_id' => $category->id,
                'product_category' => 'part',
                'is_serialized' => false,
                'selling_price' => $sellingPrice,
            ]);
        }

        $purchase = PurchaseHeader::create([
            'supplier_id' => $supplier->id,
            'date' => '2026-06-20',
            'type' => 'purchase',
            'reference' => 'مخزون أولي لقطع الغيار',
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
