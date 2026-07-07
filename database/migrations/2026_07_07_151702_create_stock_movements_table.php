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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // المنتج
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // لو Mobile Item
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();

            // نوع الحركة
            $table->enum('movement_type', [
                'opening_stock',
                'purchase',
                'sale',
                'sales_return',
                'purchase_return',
                'used_purchase',
                'used_sale',
                'used_return',
                'repair_usage',
                'damaged',
                'lost',
                'stock_adjustment',
            ]);

            // IN أو OUT
            $table->enum('movement', [
                'in',
                'out',
            ]);

            // الكمية
            $table->integer('quantity');

            // تكلفة الوحدة وقت الحركة
            $table->decimal('unit_cost', 12, 2)->nullable();

            // الجدول الذي سبب الحركة
            $table->string('reference_type');

            // رقم العملية
            $table->unsignedBigInteger('reference_id');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
