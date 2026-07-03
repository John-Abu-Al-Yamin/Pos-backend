<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'موبايلات',
            'إكسسوارات',
            'قطع غيار',
            'أجهزة لوحية',
            'سماعات',
            'شواحن',
            'كابلات',
            'حقائب وأغطية',
        ];

        foreach ($categories as $name) {
            Category::create(['name' => $name]);
        }
    }
}
