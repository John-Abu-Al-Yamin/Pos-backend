<?php

namespace App\Services\Dashboard;

use App\Services\Reports\DailyReportSummaryService;
use App\Services\Reports\ExpenseReportService;
use Illuminate\Support\Carbon;

class DashboardChartService
{
    public function __construct(
        private DashboardCacheService $cache,
        private DailyReportSummaryService $dailyReportSummary,
        private ExpenseReportService $expenseReport,
    ) {}

    public function charts(
        Carbon $dateFrom,
        Carbon $dateTo,
        array $current,
        ?array $todaySummary,
        bool $includeFinancials,
    ): array {
        $charts = [
            'daily_summary' => $this->dailySummary($dateFrom, $dateTo, $current, $todaySummary, $includeFinancials),
            'revenue_breakdown' => [
                'sales' => $current['sales']['net_revenue'],
                'maintenance' => $current['maintenance']['total_revenue'],
            ],
        ];

        if ($includeFinancials) {
            $charts['expense_breakdown'] = $this->expenseBreakdown($dateFrom, $dateTo);
        }

        return $charts;
    }

    private function dailySummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        array $current,
        ?array $todaySummary,
        bool $includeFinancials,
    ): array {
        $key = implode(':', [
            'daily-summary-chart',
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $includeFinancials ? 'financial' : 'operational',
        ]);

        $rows = collect($this->cache->remember($key, 300, fn () =>
            $this->dailyReportSummary->dashboardChartRows($dateFrom, $dateTo, $includeFinancials)
        ));

        $today = now()->toDateString();

        if ($dateFrom->toDateString() <= $today && $dateTo->toDateString() >= $today) {
            $rows->put($today, $this->dailyReportSummary->dashboardChartPoint(
                (object) $this->dailySummaryRow($today, $todaySummary ?? $current),
                $includeFinancials,
            ));
        }

        if ($rows->isEmpty() && $dateFrom->isSameDay($dateTo)) {
            $rows->put($dateFrom->toDateString(), $this->dailyReportSummary->dashboardChartPoint(
                (object) $this->dailySummaryRow($dateFrom->toDateString(), $current),
                $includeFinancials,
            ));
        }

        return $rows->sortKeys()->values()->toArray();
    }

    private function expenseBreakdown(Carbon $dateFrom, Carbon $dateTo): array
    {
        $key = implode(':', [
            'expense-breakdown',
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
        ]);

        return $this->cache->remember($key, 120, fn () => collect($this->expenseReport->getByCategory(
            $dateFrom,
            $dateTo,
            basis: 'cash',
        ))->take(5)->values()->toArray());
    }

    private function dailySummaryRow(string $date, array $summary): array
    {
        return [
            'date' => $date,
            'sales_invoice_count' => $summary['sales']['transaction_count'],
            'sales_net_revenue' => $summary['sales']['net_revenue'],
            'sales_cogs' => $summary['sales']['total_cogs'],
            'sales_profit' => $summary['sales']['gross_profit'],
            'purchase_total' => $summary['purchases']['total_purchase_amount'] ?? 0,
            'maintenance_ticket_count' => $summary['maintenance']['total_tickets'],
            'maintenance_delivered_count' => $summary['maintenance']['delivered_tickets'],
            'maintenance_total_revenue' => $summary['maintenance']['total_revenue'],
            'maintenance_profit' => $summary['maintenance']['gross_profit'],
            'expense_paid' => $summary['expenses']['paid_amount'] ?? 0,
            'salary_confirmed' => $summary['salaries']['confirmed_amount'] ?? 0,
        ];
    }
}
