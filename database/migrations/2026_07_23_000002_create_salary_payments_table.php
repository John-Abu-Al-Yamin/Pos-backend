<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_number')->unique();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->date('payment_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('payment_date');
            $table->index('status');

            $table->index([
                'user_id',
                'period_start',
                'period_end'
            ], 'salary_payments_period_lookup');

            $table->unique([
                'user_id',
                'period_start',
                'period_end'
            ], 'salary_payments_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
