<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyReportSummaryService
{
    public function __construct(
        private ProfitLossService $profitLoss,
    ) {}

    public function dashboardChartRows(Carbon $dateFrom, Carbon $dateTo, bool $includeFinancials): array
    {
        return DB::table('daily_report_summaries')
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->map(fn ($row) => $this->dashboardChartPoint($row, $includeFinancials))
            ->toArray();
    }

    public function dashboardChartPoint(object $row, bool $includeFinancials): array
    {
        $point = [
            'period' => $row->date,
            'sales_net_revenue' => (float) $row->sales_net_revenue,
            'sales_invoice_count' => (int) $row->sales_invoice_count,
            'maintenance_total_revenue' => (float) $row->maintenance_total_revenue,
            'maintenance_ticket_count' => (int) $row->maintenance_ticket_count,
            'maintenance_delivered_count' => (int) $row->maintenance_delivered_count,
        ];

        if (!$includeFinancials) {
            return $point;
        }

        return array_merge($point, [
            'sales_profit' => (float) $row->sales_profit,
            'purchase_total' => (float) $row->purchase_total,
            'expense_paid' => (float) $row->expense_paid,
            'salary_confirmed' => (float) $row->salary_confirmed,
            'maintenance_profit' => (float) $row->maintenance_profit,
            'net_profit' => $this->profitLoss->calculateNetProfit(
                (float) $row->sales_net_revenue,
                (float) $row->sales_cogs,
                (float) $row->expense_paid,
                (float) $row->salary_confirmed,
                (float) $row->maintenance_profit,
            ),
        ]);
    }
}
