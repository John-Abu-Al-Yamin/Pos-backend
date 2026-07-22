<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_operations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maintenance_header_id')
                ->constrained('maintenance_headers')
                ->cascadeOnDelete();

            $table->text('description');
            $table->date('operation_date');
            $table->string('technician')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_operations');
    }
};
