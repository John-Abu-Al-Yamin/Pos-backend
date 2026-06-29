<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('selling_price', 10, 2)->default(0)->after('is_serialized');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('payment_received_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
