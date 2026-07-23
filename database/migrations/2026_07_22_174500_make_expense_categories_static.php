<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn('expense_category_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('expense_category', [
                'electricity',
                'water',
                'internet',
                'rent',
                'salary',
                'cleaning',
                'maintenance',
                'phone_bills',
                'office_supplies',
                'equipment',
                'packaging',
                'security_cameras',
                'taxes',
                'other',
            ])->after('id');
        });

        Schema::dropIfExists('expense_categories');
    }

    public function down(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->enum('name', [
                'electricity',
                'water',
                'internet',
                'rent',
                'salary',
                'cleaning',
                'maintenance',
                'phone_bills',
                'office_supplies',
                'equipment',
                'packaging',
                'security_cameras',
                'taxes',
                'other',
            ]);
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('expense_category');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
        });
    }
};
