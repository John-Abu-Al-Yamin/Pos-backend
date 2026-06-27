<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'تيك للتوزيع', 'phone' => '020 5555 0101'],
            ['name' => 'الجملة للجوال', 'phone' => '020 5555 0102'],
            ['name' => 'قطع الهواتف المباشر', 'phone' => '020 5555 0103'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
