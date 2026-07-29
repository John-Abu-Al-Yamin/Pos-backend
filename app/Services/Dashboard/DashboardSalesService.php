<?php

namespace App\Services\Dashboard;

use App\Services\Reports\SalesReportService;
use Illuminate\Support\Carbon;

class DashboardSalesService
{
    public function __construct(
        private DashboardCacheService $cache,
        private SalesReportService $salesReport,
    ) {}

    public function overview(Carbon $dateFrom, Carbon $dateTo, array $sales, bool $includeFinancials): array
    {
        $key = implode(':', [
            'sales-overview',
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $includeFinancials ? 'financial' : 'operational',
        ]);

        return $this->cache->remember($key, 60, function () use ($dateFrom, $dateTo, $sales, $includeFinancials) {
            $bestSelling = collect($this->salesReport->getBestSelling($dateFrom, $dateTo, 5))
                ->map(function (array $item) use ($includeFinancials) {
                    if ($includeFinancials) {
                        return $item;
                    }

                    unset($item['total_cogs'], $item['gross_profit']);
                    return $item;
                })
                ->values()
                ->toArray();

            return [
                'total_returns' => $sales['total_returns'],
                'total_quantity_sold' => $sales['total_quantity_sold'],
                'returns_count' => $this->salesReport->getReturnsSummary($dateFrom, $dateTo)['return_transaction_count'],
                'best_selling' => $bestSelling,
            ];
        });
    }
}
