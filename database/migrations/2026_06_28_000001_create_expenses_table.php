<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('category', [
                'Rent',
                'Salaries',
                'Electricity',
                'Water',
                'Internet',
                'Maintenance',
                'Transportation',
                'Office Supplies',
                'Cleaning',
                'Marketing',
                'Taxes',
                'Miscellaneous',
            ]);
            $table->decimal('amount', 10, 2);
            $table->date('expense_date')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
