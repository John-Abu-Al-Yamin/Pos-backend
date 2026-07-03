<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'مدير',
            'email' => 'admin@pos.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'الموظفة سارة',
            'email' => 'sarah@pos.com',
            'password' => 'password',
            'role' => 'employee',
        ]);

        User::create([
            'name' => 'الموظف أحمد',
            'email' => 'ahmed@pos.com',
            'password' => 'password',
            'role' => 'employee',
        ]);

        User::create([
            'name' => 'الموظف خالد',
            'email' => 'khaled@pos.com',
            'password' => 'password',
            'role' => 'employee',
        ]);
    }
}
