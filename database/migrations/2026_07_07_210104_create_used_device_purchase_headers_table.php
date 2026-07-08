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
        Schema::create('used_device_purchase_headers', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number')->unique();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->enum('status', [
                'draft',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->decimal('total_amount', 12, 2)->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('used_device_purchase_headers');
    }
};
