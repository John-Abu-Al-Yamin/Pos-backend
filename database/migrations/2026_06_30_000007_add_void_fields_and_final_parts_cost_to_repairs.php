<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repairs', function (Blueprint $table) {
            $table->decimal('final_parts_cost', 10, 2)->nullable()->after('parts_cost');
            $table->timestamp('voided_at')->nullable()->after('completed_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('voided_at');
            $table->string('void_reason')->nullable()->after('voided_by');
        });

        if (config('database.default') !== 'sqlite') {
            Schema::table('repairs', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            Schema::table('repairs', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }

        Schema::table('repairs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['final_parts_cost', 'voided_at', 'void_reason']);
        });
    }
};
