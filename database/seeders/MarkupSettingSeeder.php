<?php

namespace Database\Seeders;

use App\Models\MarkupSetting;
use Illuminate\Database\Seeder;

class MarkupSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['product_type' => 'new_mobile', 'profit_percentage' => 15],
            ['product_type' => 'used_mobile', 'profit_percentage' => 20],
            ['product_type' => 'accessory', 'profit_percentage' => 30],
            ['product_type' => 'spare_part', 'profit_percentage' => 25],
        ];

        foreach ($settings as $setting) {
            MarkupSetting::create($setting);
        }
    }
}
