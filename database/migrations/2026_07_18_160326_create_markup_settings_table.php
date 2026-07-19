<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markup_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('product_type', [
                'new_mobile',
                'used_mobile',
                'accessory',
                'spare_part'
            ]);
            $table->decimal('profit_percentage', 5, 2);
            $table->timestamps();
            $table->unique('product_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markup_settings');
    }
};
