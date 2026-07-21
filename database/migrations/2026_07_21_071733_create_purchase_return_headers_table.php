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
        Schema::create('purchase_return_headers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_header_id')->constrained('purchase_headers')->restrictOnDelete();
            $table->string('return_number')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_refund_amount', 12, 2)->default(0);
            // General reason for the return
            $table->text('reason')->nullable();
            // Dates
            $table->date('return_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_headers');
    }
};
