<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payment_items', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'rate', 'notes']);
        });
    }

    public function down(): void
    {
        Schema::table('salary_payment_items', function (Blueprint $table) {
            $table->decimal('quantity', 8, 2)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
        });
    }
};
