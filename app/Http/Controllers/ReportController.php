<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\DateRangeRequest;
use App\Http\Requests\Report\InventoryReportRequest;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\MaintenanceReportService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\ProfitLossService;
use App\Services\Reports\SalaryReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private SalesReportService $salesReport,
        private PurchaseReportService $purchaseReport,
        private MaintenanceReportService $maintenanceReport,
        private ExpenseReportService $expenseReport,
        private InventoryReportService $inventoryReport,
        private ProfitLossService $profitLoss,
        private SalaryReportService $salaryReport,
    ) {}

    public function sales(DateRangeRequest $request)
    {
        $this->authorizeReport($request, 'sales');
        $data = $this->salesReport->generate($request->validated());
        $data = $this->withReportMetadata($data, $request, 'created_at', 'Sales are reported by invoice creation time because sales_headers has no separate sale_date column; this is the committed sale event timestamp.');
        return \App\Http\Responses\ApiResponse::success(
            message: 'Sales report generated successfully',
            data: $data,
        );
    }

    public function purchases(DateRangeRequest $request)
    {
        $this->authorizeReport($request, 'purchases');
        $data = $this->purchaseReport->generate($request->validated());
        $data = $this->withReportMetadata($data, $request, 'completed_at', 'Purchases are reported by completed_at because draft purchases are not financial events until completion.');
        return \App\Http\Responses\ApiResponse::success(
            message: 'Purchase report generated successfully',
            data: $data,
        );
    }

    public function maintenance(DateRangeRequest $request)
    {
        $this->authorizeReport($request, 'maintenance');
        $data = $this->maintenanceReport->generate($request->validated());
        $data = $this->withReportMetadata($data, $request, 'delivery_date', 'Maintenance revenue is reported by delivery_date because delivery represents the completed, billable business event; receiving metrics use received_date and repaired used-part metrics use repaired_at.');
        return \App\Http\Responses\ApiResponse::success(
            message: 'Maintenance report generated successfully',
            data: $data,
        );
    }

    public function expenses(DateRangeRequest $request)
    {
        $this->authorizeReport($request, 'expenses');
        $data = $this->expenseReport->generate($request->validated());
        $basis = $request->validated()['expense_basis'] ?? 'accrual';
        $data = $this->withReportMetadata($data, $request, $basis === 'cash' ? 'payment_date' : 'expense_date', 'Expenses use expense_date for accrual basis and payment_date for cash basis.');
        return \App\Http\Responses\ApiResponse::success(
            message: 'Expense report generated successfully',
            data: $data,
        );
    }

    public function inventory(InventoryReportRequest $request)
    {
        $this->authorizeReport($request, 'inventory');
        $data = $this->inventoryReport->generate($request->validated());
        $data = $this->withReportMetadata($data, $request, 'current_stock_snapshot', 'Inventory value and stock levels are current snapshots; movement summaries use stock_movements.created_at for movement event timing.');
        return \App\Http\Responses\ApiResponse::success(
            message: 'Inventory report generated successfully',
            data: $data,
        );
    }

    public function profitLoss(DateRangeRequest $request)
    {
        $this->authorizeReport($request, 'profit-loss');
        $data = $this->profitLoss->generate($request->validated());
        $data = $this->withReportMetadata($data, $request, 'module_business_dates', 'Profit & loss uses each module business date: sales created_at, purchase completed_at, maintenance delivery_date, expense payment_date for cash expenses, and salary payment_date.');
        return \App\Http\Responses\ApiResponse::success(
            message: 'Profit & loss report generated successfully',
            data: $data,
        );
    }

    public function salaries(DateRangeRequest $request)
    {
        $this->authorizeReport($request, 'salaries');
        $data = $this->salaryReport->generate($request->validated());
        $data = $this->withReportMetadata($data, $request, 'payment_date', 'Salary reporting uses payment_date because payroll affects financial reporting when paid.');
        return \App\Http\Responses\ApiResponse::success(
            message: 'Salary report generated successfully',
            data: $data,
        );
    }

    private function authorizeReport(Request $request, string $report): void
    {
        $user = $request->user();

        if ($user?->role === 'admin') {
            return;
        }

        $employeeAllowedReports = [];

        abort_unless(
            $user && in_array($report, $employeeAllowedReports, true),
            403,
            'You do not have permission to access this report.'
        );
    }

    private function withReportMetadata(array $data, Request $request, string $businessDateField, string $businessDateReason): array
    {
        return array_merge([
            'report_period' => method_exists($request, 'resolvedPeriod') ? $request->resolvedPeriod() : null,
            'business_date' => [
                'field' => $businessDateField,
                'reason' => $businessDateReason,
            ],
        ], $data);
    }
}
