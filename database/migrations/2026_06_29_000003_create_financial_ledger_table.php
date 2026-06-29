<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->decimal('amount', 10, 2);
            $table->enum('direction', ['inflow', 'outflow']);
            $table->timestamp('occurred_at');
            $table->nullableMorphs('reference');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger');
    }
};
