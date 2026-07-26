<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_headers', function (Blueprint $table) {
            $table->timestamp('repaired_at')->nullable()->after('delivery_date');
            $table->index(['status', 'repaired_at', 'customer_id'], 'maintenance_headers_repaired_report_index');
        });

        DB::table('maintenance_headers')
            ->where('status', 'repaired')
            ->whereNull('repaired_at')
            ->update(['repaired_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('maintenance_headers', function (Blueprint $table) {
            $table->dropIndex('maintenance_headers_repaired_report_index');
            $table->dropColumn('repaired_at');
        });
    }
};
