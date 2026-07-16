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
            'تابلت',
            'سماعات',
            'شواحن',
          
        ];

        foreach ($categories as $name) {
            Category::create(['name' => $name]);
        }
    }
}
