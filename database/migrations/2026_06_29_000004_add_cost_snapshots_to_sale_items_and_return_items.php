<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('total_cost', 10, 2)->default(0)->after('line_total');
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->default(0)->after('refund_amount');
            $table->decimal('total_cost', 10, 2)->default(0)->after('unit_cost');
            $table->decimal('unit_price', 10, 2)->default(0)->after('total_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost', 'unit_price']);
        });
    }
};
