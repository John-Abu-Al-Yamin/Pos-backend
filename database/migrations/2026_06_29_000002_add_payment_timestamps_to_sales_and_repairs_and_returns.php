<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('payment_received_at')->nullable()->after('payment_method');
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit');
            $table->decimal('final_payment', 10, 2)->default(0)->after('estimated_cost');
            $table->timestamp('final_paid_at')->nullable()->after('payment_status');
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->timestamp('refund_processed_at')->nullable()->after('refund_total');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('payment_received_at');
        });

        Schema::table('repairs', function (Blueprint $table) {
            $table->dropColumn(['deposit_paid_at', 'final_payment', 'final_paid_at']);
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn('refund_processed_at');
        });
    }
};
