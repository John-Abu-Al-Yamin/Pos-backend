<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@pos.com',
            'password' => 'password',
        ]);
        $admin->role = 'admin';
        $admin->save();

        $employee = User::create([
            'name' => 'Staff Sarah',
            'email' => 'sarah@pos.com',
            'password' => 'password',
        ]);
        $employee->role = 'employee';
        $employee->save();
    }
}
