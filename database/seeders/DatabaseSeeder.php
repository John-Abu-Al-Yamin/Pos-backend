<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SupplierSeeder::class,
            CustomerSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            StockSeeder::class,
            SaleSeeder::class,
            ReturnSeeder::class,
            SparePartSeeder::class,
            RepairSeeder::class,
        ]);
    }
}
