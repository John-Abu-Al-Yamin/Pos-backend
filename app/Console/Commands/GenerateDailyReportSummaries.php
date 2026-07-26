<?php

namespace App\Console\Commands;

use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\MaintenanceReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\SalaryReportService;
use App\Services\Reports\SalesReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class GenerateDailyReportSummaries extends Command
{
    protected $signature = 'reports:generate-daily-summaries {--date= : Specific date (Y-m-d) to generate, defaults to yesterday}';
    protected $description = 'Generate daily report summary records for historical reporting performance';

    public function handle(): int
    {
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subDay();

        $dayStart = $targetDate->copy()->startOfDay();
        $dayEnd = $targetDate->copy()->endOfDay();
        $dateStr = $targetDate->format('Y-m-d');

        $this->info("Generating daily summary for: {$dateStr}");

        $salesSummary = app(SalesReportService::class)->getSummary($dayStart, $dayEnd);
        $purchaseSummary = app(PurchaseReportService::class)->getSummary($dayStart, $dayEnd);
        $maintenanceSummary = app(MaintenanceReportService::class)->getSummary($dayStart, $dayEnd);
        $expenseSummary = app(ExpenseReportService::class)->getSummary($dayStart, $dayEnd);
        $salarySummary = app(SalaryReportService::class)->getSummary($dayStart, $dayEnd, 'confirmed');

        DB::table('daily_report_summaries')->updateOrInsert(
            ['date' => $dateStr],
            [
                'sales_invoice_count' => (int) $salesSummary['transaction_count'],
                'sales_revenue' => (float) $salesSummary['total_revenue'],
                'sales_discount' => (float) $salesSummary['total_discount'],
                'sales_net_revenue' => (float) $salesSummary['net_revenue'],
                'sales_cogs' => (float) $salesSummary['total_cogs'],
                'sales_profit' => (float) $salesSummary['gross_profit'],
                'sales_return_refund' => (float) $salesSummary['total_returns'],
                'sales_return_cogs' => (float) $salesSummary['return_cogs'],
                'purchase_invoice_count' => (int) $purchaseSummary['transaction_count'],
                'purchase_total' => (float) $purchaseSummary['total_purchase_amount'],
                'purchase_return_refund' => (float) $purchaseSummary['total_return_refund'],
                'maintenance_ticket_count' => (int) $maintenanceSummary['total_tickets'],
                'maintenance_delivered_count' => (int) $maintenanceSummary['delivered_tickets'],
                'maintenance_labor_revenue' => (float) $maintenanceSummary['total_labor_revenue'],
                'maintenance_parts_revenue' => (float) $maintenanceSummary['total_parts_revenue'],
                'maintenance_parts_cost' => (float) $maintenanceSummary['total_parts_cost'],
                'maintenance_total_revenue' => (float) $maintenanceSummary['total_revenue'],
                'maintenance_profit' => (float) $maintenanceSummary['gross_profit'],
                'expense_total' => (float) $expenseSummary['total_amount'],
                'expense_paid' => (float) $expenseSummary['paid_amount'],
                'expense_pending' => (float) $expenseSummary['pending_amount'],
                'salary_total' => (float) $salarySummary['total_amount'],
                'salary_confirmed' => (float) $salarySummary['confirmed_amount'],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->info("Daily summary for {$dateStr} generated successfully.");
        return self::SUCCESS;
    }
}
