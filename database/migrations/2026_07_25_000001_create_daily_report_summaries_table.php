<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('sales_invoice_count')->default(0);
            $table->decimal('sales_revenue', 15, 2)->default(0);
            $table->decimal('sales_discount', 15, 2)->default(0);
            $table->decimal('sales_net_revenue', 15, 2)->default(0);
            $table->decimal('sales_cogs', 15, 2)->default(0);
            $table->decimal('sales_profit', 15, 2)->default(0);
            $table->decimal('sales_return_refund', 15, 2)->default(0);
            $table->decimal('sales_return_cogs', 15, 2)->default(0);
            $table->unsignedInteger('purchase_invoice_count')->default(0);
            $table->decimal('purchase_total', 15, 2)->default(0);
            $table->decimal('purchase_return_refund', 15, 2)->default(0);
            $table->unsignedInteger('maintenance_ticket_count')->default(0);
            $table->unsignedInteger('maintenance_delivered_count')->default(0);
            $table->decimal('maintenance_labor_revenue', 15, 2)->default(0);
            $table->decimal('maintenance_parts_revenue', 15, 2)->default(0);
            $table->decimal('maintenance_parts_cost', 15, 2)->default(0);
            $table->decimal('maintenance_total_revenue', 15, 2)->default(0);
            $table->decimal('maintenance_profit', 15, 2)->default(0);
            $table->decimal('expense_total', 15, 2)->default(0);
            $table->decimal('expense_paid', 15, 2)->default(0);
            $table->decimal('expense_pending', 15, 2)->default(0);
            $table->decimal('salary_total', 15, 2)->default(0);
            $table->decimal('salary_confirmed', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_summaries');
    }
};
