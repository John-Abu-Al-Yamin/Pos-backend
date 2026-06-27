<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => 'سارة أحمد', 'phone' => '07700 900001'],
            ['name' => 'جمال سعيد', 'phone' => '07700 900002'],
            ['name' => 'مريم علي', 'phone' => '07700 900003'],
            ['name' => 'ميخائيل إبراهيم', 'phone' => '07700 900004'],
            ['name' => 'ليلى محمد', 'phone' => '07700 900005'],
            ['name' => 'وليد حسن', 'phone' => '07700 900006'],
            ['name' => 'سلمى عمر', 'phone' => '07700 900007'],
            ['name' => 'دانيال يوسف', 'phone' => '07700 900008'],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}
