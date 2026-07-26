<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->default(0)->after('unit_refund_amount');
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->default(0)->after('unit_refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
