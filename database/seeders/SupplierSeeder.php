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
            ['name' => 'الإكسسوارات الذهبية', 'phone' => '020 5555 0104'],
            ['name' => 'شواحن وكابلات الممتاز', 'phone' => '020 5555 0105'],
            ['name' => 'الأجهزة اللوحية المتحدة', 'phone' => '020 5555 0106'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
