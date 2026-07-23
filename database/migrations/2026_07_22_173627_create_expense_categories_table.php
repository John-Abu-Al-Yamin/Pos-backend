<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->enum('name', [
                'electricity',        // كهرباء
                'water',              // مياه
                'internet',           // إنترنت
                'rent',               // إيجار
                'salary',             // مرتبات
                'cleaning',           // أدوات تنظيف
                'maintenance',        // صيانة المحل
                'phone_bills',        // فواتير هاتف
                'office_supplies',    // أدوات مكتبية
                'equipment',          // شراء معدات للمحل
                'packaging',          // أكياس وعلب وتغليف
                'security_cameras',   // كاميرات ومراقبة
                'taxes',              // ضرائب
                'other'               // أخرى
            ]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
