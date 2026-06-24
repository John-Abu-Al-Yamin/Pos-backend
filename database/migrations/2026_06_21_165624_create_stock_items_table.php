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
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();
            // بيانات القطعة
            $table->string('serial_number')->nullable()->unique();
            $table->decimal('cost_price', 10, 2);
            $table->enum('condition', ['new', 'excellent', 'good', 'fair'])
                ->default('new');

            $table->enum('status', [
                'available',
                'sold',
                'reserved',
                'damaged',
                'returned',
                'voided'
            ])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
