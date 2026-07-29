<?php

namespace App\Services\Dashboard;

use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\MaintenanceReportService;
use App\Services\Reports\ProfitLossService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\SalaryReportService;
use App\Services\Reports\SalesReportService;
use Illuminate\Support\Carbon;

class DashboardSummaryService
{
    public function __construct(
        private DashboardCacheService $cache,
        private SalesReportService $salesReport,
        private PurchaseReportService $purchaseReport,
        private MaintenanceReportService $maintenanceReport,
        private ExpenseReportService $expenseReport,
        private InventoryReportService $inventoryReport,
        private ProfitLossService $profitLoss,
        private SalaryReportService $salaryReport,
    ) {}

    public function periodSummary(Carbon $dateFrom, Carbon $dateTo, bool $includeFinancials): array
    {
        $key = implode(':', [
            'summary',
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $includeFinancials ? 'financial' : 'operational',
        ]);

        return $this->cache->remember($key, 60, function () use ($dateFrom, $dateTo, $includeFinancials) {
            $sales = $this->salesReport->getSummary($dateFrom, $dateTo);
            $maintenance = $this->maintenanceReport->getSummary($dateFrom, $dateTo);
            $purchases = $includeFinancials
                ? $this->purchaseReport->getSummary($dateFrom, $dateTo)
                : null;
            $expenses = $includeFinancials
                ? $this->expenseReport->getSummary($dateFrom, $dateTo, basis: 'cash')
                : null;
            $salaries = $includeFinancials
                ? $this->salaryReport->getSummary($dateFrom, $dateTo, 'confirmed')
                : null;
            $profitLoss = $includeFinancials
                ? $this->profitLoss->generateFromSummaries($dateFrom, $dateTo, $sales, $purchases, $maintenance, $expenses, $salaries)
                : null;

            return compact('sales', 'maintenance', 'purchases', 'expenses', 'salaries', 'profitLoss');
        });
    }

    public function inventorySummary(): array
    {
        return $this->cache->remember('inventory-summary', 60, fn () => [
            'stock_value' => $this->inventoryReport->getStockValue(),
            'low_stock' => $this->inventoryReport->getLowStock(),
            'by_product_type' => $this->inventoryReport->getByProductType(),
        ]);
    }
}
