<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_assignments', function (Blueprint $table) {
            $table->dropUnique('salary_assignments_user_date_unique');

            $table->unique('user_id', 'salary_assignments_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('salary_assignments', function (Blueprint $table) {
            $table->dropUnique('salary_assignments_user_unique');

            $table->unique(['user_id', 'effective_date'], 'salary_assignments_user_date_unique');
        });
    }
};
