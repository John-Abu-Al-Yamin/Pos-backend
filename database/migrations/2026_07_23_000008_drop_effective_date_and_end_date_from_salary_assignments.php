<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_assignments', function (Blueprint $table) {
            $table->dropIndex('salary_assignments_active_lookup');

            $table->dropColumn(['effective_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('salary_assignments', function (Blueprint $table) {
            $table->date('effective_date');
            $table->date('end_date')->nullable();

            $table->index(['user_id', 'effective_date', 'end_date'], 'salary_assignments_active_lookup');
        });
    }
};
