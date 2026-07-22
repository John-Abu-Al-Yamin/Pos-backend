<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_used_parts', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->after('unit_price')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_used_parts', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
