<?php

namespace App\Services\Dashboard;

use App\Models\User;

class DashboardService
{
    public function __construct(
        private DashboardPeriodService $periods,
        private DashboardSummaryService $summaries,
        private DashboardOperationsService $operations,
        private DashboardChartService $charts,
        private DashboardSalesService $sales,
        private DashboardAlertService $alerts,
        private DashboardRecentActivityService $recentActivity,
        private DashboardResponseBuilder $response,
    ) {}

    public function generate(array $filters, ?User $user, array $resolvedPeriod): array
    {
        [$dateFrom, $dateTo] = $this->periods->dateRange($filters);
        [$previousFrom, $previousTo] = $this->periods->previousPeriod($dateFrom, $dateTo);
        $includeFinancials = $user?->can('view-dashboard-financials') ?? false;

        $current = $this->summaries->periodSummary($dateFrom, $dateTo, $includeFinancials);
        $previous = $this->summaries->periodSummary($previousFrom, $previousTo, $includeFinancials);
        $todaySummary = $this->todaySummary($dateFrom, $dateTo, $current, $includeFinancials);

        $inventory = $this->response->inventory(
            $this->summaries->inventorySummary(),
            $includeFinancials,
        );
        $operations = $this->operations->overview($dateFrom, $dateTo, $includeFinancials);

        return [
            'meta' => [
                'period' => $resolvedPeriod,
                'comparison_period' => [
                    'date_from' => $previousFrom->toDateString(),
                    'date_to' => $previousTo->toDateString(),
                ],
                'generated_at' => now()->toIso8601String(),
                'currency' => 'EGP',
                'role' => $user?->role,
                'is_admin' => $includeFinancials,
            ],
            'kpis' => $this->response->kpis($current, $inventory, $includeFinancials),
            'comparison' => $this->response->comparison($current, $previous, $includeFinancials),
            'charts' => $this->charts->charts($dateFrom, $dateTo, $current, $todaySummary, $includeFinancials),
            'sales' => $this->sales->overview($dateFrom, $dateTo, $current['sales'], $includeFinancials),
            'purchases' => $this->response->purchases($current['purchases'], $includeFinancials),
            'operations' => $this->response->operations($operations, $includeFinancials),
            'alerts' => $this->alerts->alerts($inventory, $operations, $includeFinancials),
            'inventory' => $inventory,
            'maintenance' => $this->response->maintenance(
                $current['maintenance'],
                $operations['maintenance'],
                $includeFinancials,
            ),
            'recent_activity' => $this->recentActivity->latest($includeFinancials),
        ];
    }

    private function todaySummary($dateFrom, $dateTo, array $current, bool $includeFinancials): ?array
    {
        $today = now();

        if ($dateFrom->isSameDay($dateTo) || !$today->betweenIncluded($dateFrom, $dateTo)) {
            return $dateFrom->isSameDay($today) ? $current : null;
        }

        return $this->summaries->periodSummary(
            $today->copy()->startOfDay(),
            $today->copy()->endOfDay(),
            $includeFinancials,
        );
    }
}
