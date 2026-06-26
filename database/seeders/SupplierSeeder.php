<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'TechDistrib Ltd', 'phone' => '020 5555 0101'],
            ['name' => 'MobileWholesale UK', 'phone' => '020 5555 0102'],
            ['name' => 'PhoneParts Direct', 'phone' => '020 5555 0103'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
