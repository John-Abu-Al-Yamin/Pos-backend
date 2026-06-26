<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_headers', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_header_id']);

            $table->foreign('purchase_header_id')
                ->references('id')
                ->on('purchase_headers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_header_id']);

            $table->foreign('purchase_header_id')
                ->references('id')
                ->on('purchase_headers')
                ->cascadeOnDelete();
        });

        Schema::table('purchase_headers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
