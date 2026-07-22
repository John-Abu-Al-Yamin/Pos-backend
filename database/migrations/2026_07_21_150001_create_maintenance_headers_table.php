<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_headers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maintenance_device_id')
                ->nullable()
                ->constrained('maintenance_devices')
                ->nullOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->string('ticket_number')->unique();
            $table->enum('status', [
                'pending',
                'under_repair',
                'waiting_parts',
                'repaired',
                'delivered',
                'cancelled',
            ])->default('pending');

            $table->text('problem_description');
            $table->date('received_date');
            $table->date('delivery_date')->nullable();

            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('advance_payment', 12, 2)->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_headers');
    }
};
