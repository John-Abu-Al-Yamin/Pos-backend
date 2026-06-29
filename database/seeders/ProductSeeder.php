<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $apple = Category::where('name', 'أبل')->first();
        $samsung = Category::where('name', 'سامسونج')->first();
        $google = Category::where('name', 'جوجل')->first();
        $accessories = Category::where('name', 'إكسسوارات')->first();

        $products = [
            // Apple products (serialized)
            ['name' => 'iPhone 15 Pro Max', 'category_id' => $apple->id, 'is_serialized' => true, 'selling_price' => 1299.00],
            ['name' => 'iPhone 15', 'category_id' => $apple->id, 'is_serialized' => true, 'selling_price' => 999.00],
            ['name' => 'iPhone 14', 'category_id' => $apple->id, 'is_serialized' => true, 'selling_price' => 799.00],
            ['name' => 'iPhone SE 3rd Gen', 'category_id' => $apple->id, 'is_serialized' => true, 'selling_price' => 499.00],
            // Samsung products (serialized)
            ['name' => 'Galaxy S24 Ultra', 'category_id' => $samsung->id, 'is_serialized' => true, 'selling_price' => 1149.00],
            ['name' => 'Galaxy S24', 'category_id' => $samsung->id, 'is_serialized' => true, 'selling_price' => 749.00],
            ['name' => 'Galaxy A55', 'category_id' => $samsung->id, 'is_serialized' => true, 'selling_price' => 399.00],
            ['name' => 'Galaxy Z Flip 6', 'category_id' => $samsung->id, 'is_serialized' => true, 'selling_price' => 1099.00],
            // Google products (serialized)
            ['name' => 'Pixel 8 Pro', 'category_id' => $google->id, 'is_serialized' => true, 'selling_price' => 999.00],
            ['name' => 'Pixel 8', 'category_id' => $google->id, 'is_serialized' => true, 'selling_price' => 699.00],
            ['name' => 'Pixel 7a', 'category_id' => $google->id, 'is_serialized' => true, 'selling_price' => 499.00],
            // Accessories (non-serialized)
            ['name' => 'شاحن سريع USB-C', 'category_id' => $accessories->id, 'is_serialized' => false, 'selling_price' => 19.99],
            ['name' => 'جراب هاتف سيليكون', 'category_id' => $accessories->id, 'is_serialized' => false, 'selling_price' => 14.99],
            ['name' => 'حامي شاشة زجاجي مقسى', 'category_id' => $accessories->id, 'is_serialized' => false, 'selling_price' => 9.99],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
