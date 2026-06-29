<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // No-dependency seeders
            CategorySeeder::class,
            SupplierSeeder::class,
            CustomerSeeder::class,
            UserSeeder::class,

            // Product-related seeders
            ProductSeeder::class,
            StockSeeder::class,
            SparePartSeeder::class,

            // Transaction seeders
            SaleSeeder::class,
            ReturnSeeder::class,
            RepairSeeder::class,

            // Financial & inventory seeders
            ExpenseSeeder::class,
            InventoryAdjustmentSeeder::class,
            FinancialLedgerSeeder::class,
        ]);
    }
}
