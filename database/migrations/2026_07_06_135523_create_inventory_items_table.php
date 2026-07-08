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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('internal_serial')->unique();
            

            $table->enum('status', [
                'available',
                'sold',
                'returned',
                'under_repair',
                'damaged',
            ])->default('available');
            $table->decimal('cost_price', 12, 2);
            $table->unsignedTinyInteger('battery_health')->nullable();

            $table->string('screen_condition')->nullable();
            $table->string('body_condition')->nullable();
            $table->boolean('fingerprint_working')->nullable();
            $table->boolean('face_id_working')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
