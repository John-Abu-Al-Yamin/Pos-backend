<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => 'Sarah Johnson', 'phone' => '07700 900001'],
            ['name' => 'James Smith', 'phone' => '07700 900002'],
            ['name' => 'Emma Williams', 'phone' => '07700 900003'],
            ['name' => 'Michael Brown', 'phone' => '07700 900004'],
            ['name' => 'Olivia Davis', 'phone' => '07700 900005'],
            ['name' => 'William Wilson', 'phone' => '07700 900006'],
            ['name' => 'Sophia Taylor', 'phone' => '07700 900007'],
            ['name' => 'Daniel Thomas', 'phone' => '07700 900008'],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}
