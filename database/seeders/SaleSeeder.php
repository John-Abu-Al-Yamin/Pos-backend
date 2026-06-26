<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@pos.com')->first();
        $sarah = User::where('email', 'sarah@pos.com')->first();
        $customers = Customer::all()->keyBy('name');

        $salesData = [
            [
                'customer' => 'Sarah Johnson', 'user' => $admin, 'date' => '2026-04-05',
                'payment' => 'cash',
                'items' => [
                    ['product' => 'iPhone 15 Pro Max', 'qty' => 1, 'price' => 1299.00],
                    ['product' => 'iPhone 15', 'qty' => 1, 'price' => 999.00],
                ],
            ],
            [
                'customer' => 'James Smith', 'user' => $sarah, 'date' => '2026-04-10',
                'payment' => 'card',
                'items' => [
                    ['product' => 'Galaxy S24 Ultra', 'qty' => 1, 'price' => 1149.00],
                ],
            ],
            [
                'customer' => 'Emma Williams', 'user' => $admin, 'date' => '2026-04-15',
                'payment' => 'transfer',
                'items' => [
                    ['product' => 'Pixel 8 Pro', 'qty' => 1, 'price' => 999.00],
                    ['product' => 'USB-C Fast Charger', 'qty' => 1, 'price' => 19.99],
                ],
            ],
            [
                'customer' => 'Michael Brown', 'user' => $sarah, 'date' => '2026-04-20',
                'payment' => 'installment',
                'items' => [
                    ['product' => 'iPhone 15 Pro Max', 'qty' => 1, 'price' => 1299.00],
                ],
            ],
            [
                'customer' => 'Olivia Davis', 'user' => $admin, 'date' => '2026-05-05',
                'payment' => 'cash',
                'items' => [
                    ['product' => 'Galaxy S24', 'qty' => 1, 'price' => 749.00],
                    ['product' => 'Silicone Phone Case', 'qty' => 1, 'price' => 14.99],
                ],
            ],
            [
                'customer' => 'William Wilson', 'user' => $sarah, 'date' => '2026-05-10',
                'payment' => 'card',
                'items' => [
                    ['product' => 'iPhone 15', 'qty' => 1, 'price' => 999.00],
                    ['product' => 'Tempered Glass Screen Protector', 'qty' => 1, 'price' => 9.99],
                ],
            ],
            [
                'customer' => 'Sophia Taylor', 'user' => $admin, 'date' => '2026-05-18',
                'payment' => 'cash',
                'items' => [
                    ['product' => 'Galaxy Z Flip 6', 'qty' => 1, 'price' => 1099.00],
                ],
            ],
            [
                'customer' => 'Daniel Thomas', 'user' => $sarah, 'date' => '2026-05-22',
                'payment' => 'card',
                'items' => [
                    ['product' => 'Pixel 8', 'qty' => 1, 'price' => 699.00],
                    ['product' => 'Silicone Phone Case', 'qty' => 1, 'price' => 14.99],
                ],
            ],
            [
                'customer' => 'Sarah Johnson', 'user' => $admin, 'date' => '2026-06-05',
                'payment' => 'installment',
                'items' => [
                    ['product' => 'Galaxy S24 Ultra', 'qty' => 1, 'price' => 1149.00],
                ],
            ],
            [
                'customer' => 'James Smith', 'user' => $sarah, 'date' => '2026-06-12',
                'payment' => 'transfer',
                'items' => [
                    ['product' => 'iPhone 15 Pro Max', 'qty' => 1, 'price' => 1299.00],
                    ['product' => 'USB-C Fast Charger', 'qty' => 1, 'price' => 19.99],
                ],
            ],
        ];

        foreach ($salesData as $data) {
            $total = 0;
            foreach ($data['items'] as $item) {
                $total += round($item['qty'] * $item['price'], 2);
            }

            $sale = Sale::create([
                'customer_id' => $customers[$data['customer']]->id,
                'user_id' => $data['user']->id,
                'date' => $data['date'],
                'total' => $total,
                'payment_method' => $data['payment'],
                'reference_code' => 'SALE-' . str_replace('-', '', $data['date']) . '-' . str_pad((string) rand(1, 999), 4, '0', STR_PAD_LEFT),
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::where('name', $item['product'])->first();
                $lineTotal = round($item['qty'] * $item['price'], 2);

                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['qty'],
                    'unit_price' => $item['price'],
                    'line_total' => $lineTotal,
                ]);

                for ($i = 0; $i < $item['qty']; $i++) {
                    $stockItem = StockItem::where('product_id', $product->id)
                        ->where('status', 'available')
                        ->orderBy('id')
                        ->first();

                    if ($stockItem) {
                        $stockItem->status = 'sold';
                        $stockItem->save();

                        $saleItem->stockItems()->attach($stockItem->id);
                    }
                }
            }
        }
    }
}
