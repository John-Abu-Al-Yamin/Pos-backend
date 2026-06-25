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

            $table->foreignId('purchase_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // بيانات الجهاز
            $table->string('serial_number')->nullable()->unique();
            $table->decimal('cost_price', 10, 2);

            // حالة الجهاز العامة
            $table->enum('condition', [
                'new',
                'excellent',
                'good',
                'fair'
            ])->default('new');

            // تفاصيل إضافية للأجهزة المستعملة
            $table->unsignedTinyInteger('battery_health')->nullable(); // 0 - 100

            $table->enum('screen_condition', [
                'excellent',
                'good',
                'fair',
                'broken'
            ])->nullable();

            $table->enum('body_condition', [
                'excellent',
                'good',
                'fair',
                'damaged'
            ])->nullable();

            $table->boolean('face_id_working')->nullable();
            $table->boolean('fingerprint_working')->nullable();
            $table->boolean('camera_working')->nullable();
            $table->boolean('speaker_working')->nullable();
            $table->string('accessories')->nullable();


            $table->text('notes')->nullable();

            // حالة المخزون
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
