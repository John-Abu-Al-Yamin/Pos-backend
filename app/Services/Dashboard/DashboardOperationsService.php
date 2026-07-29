<?php

namespace App\Services\Dashboard;

use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\MaintenanceReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\SalaryReportService;
use Illuminate\Support\Carbon;

class DashboardOperationsService
{
    public function __construct(
        private DashboardCacheService $cache,
        private MaintenanceReportService $maintenanceReport,
        private PurchaseReportService $purchaseReport,
        private ExpenseReportService $expenseReport,
        private SalaryReportService $salaryReport,
    ) {}

    public function overview(Carbon $dateFrom, Carbon $dateTo, bool $includeFinancials): array
    {
        $key = implode(':', [
            'operations',
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $includeFinancials ? 'financial' : 'operational',
        ]);

        return $this->cache->remember($key, 45, fn () => [
            'maintenance' => $this->maintenanceReport->getOperationalSummary($dateFrom, $dateTo),
            'purchases' => $this->purchaseReport->getOperationalSummary($dateFrom, $dateTo),
            'expenses' => $this->expenseReport->getOperationalSummary($dateFrom, $dateTo),
            'salaries' => $this->salaryReport->getOperationalSummary(),
        ]);
    }
}
