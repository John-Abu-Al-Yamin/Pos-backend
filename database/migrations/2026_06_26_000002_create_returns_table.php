<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('return_date');
            $table->enum('refund_method', ['cash', 'card', 'bank_transfer']);
            $table->decimal('refund_total', 10, 2);
            $table->decimal('restocking_fee', 10, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_code')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
