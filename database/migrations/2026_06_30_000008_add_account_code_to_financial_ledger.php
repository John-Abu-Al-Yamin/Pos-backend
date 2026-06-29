<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->string('account_code', 20)->nullable()->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->dropColumn('account_code');
        });
    }
};
