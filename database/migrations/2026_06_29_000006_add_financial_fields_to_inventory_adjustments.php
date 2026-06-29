<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->decimal('total_loss_amount', 10, 2)->default(0)->after('difference');
            $table->decimal('total_gain_amount', 10, 2)->default(0)->after('total_loss_amount');
            $table->decimal('unit_cost_snapshot', 10, 2)->default(0)->after('total_gain_amount');
            $table->timestamp('voided_at')->nullable()->after('notes');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['total_loss_amount', 'total_gain_amount', 'unit_cost_snapshot', 'voided_at']);
        });
    }
};
