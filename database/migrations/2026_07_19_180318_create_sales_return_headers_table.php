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
        Schema::create('sales_return_headers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_header_id')->constrained('sales_headers')->restrictOnDelete();
            $table->string('return_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_refund_amount', 12, 2)->default(0);
            // General reason for the return
            $table->text('reason')->nullable();
            // Dates
            $table->date('return_date');
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
        Schema::dropIfExists('sales_return_headers');
    }
};
