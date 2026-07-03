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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // نوع المنتج
            $table->enum('type', ['mobile', 'accessory', 'spare_part'])->default('mobile');
            $table->unsignedSmallInteger('min_stock')->default(5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
