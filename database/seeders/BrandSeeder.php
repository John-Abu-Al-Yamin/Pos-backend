<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $brands = [
            ['name' => 'Apple', 'is_active' => true],
            ['name' => 'Samsung', 'is_active' => true],
            ['name' => 'Huawei', 'is_active' => true],
            ['name' => 'Anker', 'is_active' => true],
            ['name' => 'Oppo', 'is_active' => true],
            ['name' => 'OnePlus', 'is_active' => true],
            ['name' => 'Sony', 'is_active' => true],
            ['name' => 'Baseus', 'is_active' => true],
            ['name' => 'Xiaomi', 'is_active' => false],
            ['name' => 'Nokia', 'is_active' => false],
            ['name' => 'Google', 'is_active' => true],
            ['name' => 'Realme', 'is_active' => true],
            ['name' => 'Honor', 'is_active' => true],
            ['name' => 'LG', 'is_active' => false],
        ];
        foreach ($brands as $brand) {
            Brand::create($brand);
        }
    }
}
