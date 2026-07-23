<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('base_salary', 10, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();

            $table->enum('payment_frequency', [
                'monthly',
                'bi_weekly',
                'weekly'
            ])->default('monthly');

            $table->date('effective_date');
            $table->date('end_date')->nullable();

            $table->text('reason')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();


            // Indexes
            $table->unique([
                'user_id',
                'effective_date'
            ], 'salary_assignments_user_date_unique');


            $table->index([
                'user_id',
                'effective_date',
                'end_date'
            ], 'salary_assignments_active_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_assignments');
    }
};
