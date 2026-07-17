<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('used_device_purchase_items', function (Blueprint $table) {
            $table->string('serial_number')->unique()->nullable()->after('total_price');
            $table->unsignedTinyInteger('battery_health')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('used_device_purchase_items', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'battery_health']);
        });
    }
};
