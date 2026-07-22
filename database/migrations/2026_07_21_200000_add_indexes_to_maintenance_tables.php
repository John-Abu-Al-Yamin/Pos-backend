<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_devices', function (Blueprint $table) {
            $table->index('serial_number');
        });

        Schema::table('maintenance_headers', function (Blueprint $table) {
            $table->index('status');
            $table->index('received_date');
            $table->index('customer_id');
        });

        Schema::table('maintenance_used_parts', function (Blueprint $table) {
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_devices', function (Blueprint $table) {
            $table->dropIndex(['serial_number']);
        });

        Schema::table('maintenance_headers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['received_date']);
            $table->dropIndex(['customer_id']);
        });

        Schema::table('maintenance_used_parts', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
    }
};
