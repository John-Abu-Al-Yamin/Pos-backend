<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropUnique('salary_payments_user_period_unique');
            $table->dropIndex('salary_payments_period_lookup');

            $table->dropColumn(['period_start', 'period_end']);

            $table->date('payment_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->date('payment_date')->nullable(false)->change();

            $table->date('period_start');
            $table->date('period_end');

            $table->index(['user_id', 'period_start', 'period_end'], 'salary_payments_period_lookup');
            $table->unique(['user_id', 'period_start', 'period_end'], 'salary_payments_user_period_unique');
        });
    }
};
