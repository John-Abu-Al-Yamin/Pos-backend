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
            ['name' => 'iPhone 15 Pro Max', 'category_id' => $apple->id, 'is_serialized' => true],
            ['name' => 'iPhone 15', 'category_id' => $apple->id, 'is_serialized' => true],
            ['name' => 'iPhone 14', 'category_id' => $apple->id, 'is_serialized' => true],
            ['name' => 'iPhone SE 3rd Gen', 'category_id' => $apple->id, 'is_serialized' => true],
            // Samsung products (serialized)
            ['name' => 'Galaxy S24 Ultra', 'category_id' => $samsung->id, 'is_serialized' => true],
            ['name' => 'Galaxy S24', 'category_id' => $samsung->id, 'is_serialized' => true],
            ['name' => 'Galaxy A55', 'category_id' => $samsung->id, 'is_serialized' => true],
            ['name' => 'Galaxy Z Flip 6', 'category_id' => $samsung->id, 'is_serialized' => true],
            // Google products (serialized)
            ['name' => 'Pixel 8 Pro', 'category_id' => $google->id, 'is_serialized' => true],
            ['name' => 'Pixel 8', 'category_id' => $google->id, 'is_serialized' => true],
            ['name' => 'Pixel 7a', 'category_id' => $google->id, 'is_serialized' => true],
            // Accessories (non-serialized)
            ['name' => 'شاحن سريع USB-C', 'category_id' => $accessories->id, 'is_serialized' => false],
            ['name' => 'جراب هاتف سيليكون', 'category_id' => $accessories->id, 'is_serialized' => false],
            ['name' => 'حامي شاشة زجاجي مقسى', 'category_id' => $accessories->id, 'is_serialized' => false],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
