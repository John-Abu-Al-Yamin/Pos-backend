<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $mobileId = Category::where('name', 'موبايلات')->value('id');
        $accessoryId = Category::where('name', 'إكسسوارات')->value('id');
        $sparePartId = Category::where('name', 'قطع غيار')->value('id');

        $appleId = Brand::where('name', 'Apple')->value('id');
        $samsungId = Brand::where('name', 'Samsung')->value('id');
        $huaweiId = Brand::where('name', 'Huawei')->value('id');
        $ankerId = Brand::where('name', 'Anker')->value('id');
        $oppoId = Brand::where('name', 'Oppo')->value('id');
        $oneplusId = Brand::where('name', 'OnePlus')->value('id');
        $sonyId = Brand::where('name', 'Sony')->value('id');
        $baseusId = Brand::where('name', 'Baseus')->value('id');
        $xiaomiId = Brand::where('name', 'Xiaomi')->value('id');

        $products = [
            ['category_id' => $mobileId, 'brand_id' => $appleId, 'name' => 'iPhone 15 Pro Max', 'type' => 'mobile', 'min_stock' => 3],
            ['category_id' => $mobileId, 'brand_id' => $appleId, 'name' => 'iPhone 15 Pro', 'type' => 'mobile', 'min_stock' => 3],
            ['category_id' => $mobileId, 'brand_id' => $appleId, 'name' => 'iPhone 14', 'type' => 'mobile', 'min_stock' => 5],
            ['category_id' => $mobileId, 'brand_id' => $samsungId, 'name' => 'Galaxy S24 Ultra', 'type' => 'mobile', 'min_stock' => 3],
            ['category_id' => $mobileId, 'brand_id' => $samsungId, 'name' => 'Galaxy S24', 'type' => 'mobile', 'min_stock' => 5],
            ['category_id' => $mobileId, 'brand_id' => $samsungId, 'name' => 'Galaxy A55', 'type' => 'mobile', 'min_stock' => 10],
            ['category_id' => $mobileId, 'brand_id' => $huaweiId, 'name' => 'P60 Pro', 'type' => 'mobile', 'min_stock' => 5],
            ['category_id' => $mobileId, 'brand_id' => $huaweiId, 'name' => 'Mate 60 Pro', 'type' => 'mobile', 'min_stock' => 5],
            ['category_id' => $mobileId, 'brand_id' => $oppoId, 'name' => 'Find X7 Ultra', 'type' => 'mobile', 'min_stock' => 5],
            ['category_id' => $mobileId, 'brand_id' => $oneplusId, 'name' => 'OnePlus 12', 'type' => 'mobile', 'min_stock' => 5],
            ['category_id' => $mobileId, 'brand_id' => $sonyId, 'name' => 'Xperia 1 VI', 'type' => 'mobile', 'min_stock' => 3],
            ['category_id' => $mobileId, 'brand_id' => $xiaomiId, 'name' => 'Xiaomi 14 Ultra', 'type' => 'mobile', 'min_stock' => 5],
            ['category_id' => $mobileId, 'brand_id' => $xiaomiId, 'name' => 'Redmi Note 13 Pro', 'type' => 'mobile', 'min_stock' => 10],
            ['category_id' => $accessoryId, 'brand_id' => $ankerId, 'name' => 'Power Bank 20000mAh', 'type' => 'accessory', 'min_stock' => 10],
            ['category_id' => $accessoryId, 'brand_id' => $ankerId, 'name' => 'USB-C Cable 1m', 'type' => 'accessory', 'min_stock' => 20],
            ['category_id' => $accessoryId, 'brand_id' => $baseusId, 'name' => 'Wireless Charger', 'type' => 'accessory', 'min_stock' => 10],
            ['category_id' => $accessoryId, 'brand_id' => $baseusId, 'name' => 'Car Phone Mount', 'type' => 'accessory', 'min_stock' => 15],
            ['category_id' => $accessoryId, 'brand_id' => $samsungId, 'name' => 'Galaxy Buds FE', 'type' => 'accessory', 'min_stock' => 5],
            ['category_id' => $accessoryId, 'brand_id' => $appleId, 'name' => 'MagSafe Charger', 'type' => 'accessory', 'min_stock' => 5],
            ['category_id' => $sparePartId, 'brand_id' => $appleId, 'name' => 'iPhone 15 Screen', 'type' => 'spare_part', 'min_stock' => 3],
            ['category_id' => $sparePartId, 'brand_id' => $appleId, 'name' => 'iPhone 15 Battery', 'type' => 'spare_part', 'min_stock' => 5],
            ['category_id' => $sparePartId, 'brand_id' => $samsungId, 'name' => 'Galaxy S24 Screen', 'type' => 'spare_part', 'min_stock' => 3],
            ['category_id' => $sparePartId, 'brand_id' => $samsungId, 'name' => 'Galaxy S24 Battery', 'type' => 'spare_part', 'min_stock' => 5],
            ['category_id' => $sparePartId, 'brand_id' => $huaweiId, 'name' => 'P60 Screen', 'type' => 'spare_part', 'min_stock' => 3],
            ['category_id' => $sparePartId, 'brand_id' => $xiaomiId, 'name' => 'Redmi Note 13 Screen', 'type' => 'spare_part', 'min_stock' => 5],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
