<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_payment_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'base_salary', 'overtime', 'bonus', 'commission',
                'deduction', 'advance_repayment', 'adjustment',
            ]);
            $table->string('label');
            $table->decimal('amount', 10, 2);
            $table->decimal('quantity', 8, 2)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('salary_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payment_items');
    }
};
