<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_item_stock_item', function (Blueprint $table) {
            $table->foreignId('sale_item_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('stock_item_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->primary(['sale_item_id', 'stock_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_item_stock_item');
    }
};
