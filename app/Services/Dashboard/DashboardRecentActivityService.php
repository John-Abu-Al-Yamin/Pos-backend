<?php

namespace App\Services\Dashboard;

use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\MaintenanceReportService;
use App\Services\Reports\SalesReportService;

class DashboardRecentActivityService
{
    public function __construct(
        private SalesReportService $salesReport,
        private MaintenanceReportService $maintenanceReport,
        private ExpenseReportService $expenseReport,
        private InventoryReportService $inventoryReport,
    ) {}

    public function latest(bool $includeFinancials, int $limit = 5): array
    {
        return [
            'latest_sales' => $this->salesReport->getRecentSales($limit, $includeFinancials),
            'latest_maintenance_tickets' => $this->maintenanceReport->getRecentTickets($limit, $includeFinancials),
            'latest_expenses' => $this->expenseReport->getRecentExpenses($limit, $includeFinancials),
            'latest_stock_movements' => $this->inventoryReport->getRecentStockMovements($limit),
        ];
    }
}
