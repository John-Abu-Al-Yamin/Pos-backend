<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ProfitLossService
{
    public function __construct(
        private SalesReportService $salesReport,
        private PurchaseReportService $purchaseReport,
        private MaintenanceReportService $maintenanceReport,
        private ExpenseReportService $expenseReport,
    ) {}

    public function generate(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();

        $salesSummary = $this->salesReport->getSummary($dateFrom, $dateTo);
        $purchaseSummary = $this->purchaseReport->getSummary($dateFrom, $dateTo);
        $maintenanceSummary = $this->maintenanceReport->getSummary($dateFrom, $dateTo);
        $expenseSummary = $this->expenseReport->getSummary($dateFrom, $dateTo, null, null, 'cash');
        $salarySummary = $this->getSalarySummary($dateFrom, $dateTo);

        $salesRevenue = $salesSummary['net_revenue'];
        $cogs = $salesSummary['total_cogs'];
        $maintenanceProfit = $maintenanceSummary['gross_profit'];
        $maintenanceRevenue = $maintenanceSummary['total_revenue'];
        $expenses = $expenseSummary['paid_amount'];
        $salaries = $salarySummary['total_confirmed'];

        $grossProfit = $salesRevenue - $cogs;
        $netProfit = $salesRevenue - $cogs - $expenses - $salaries + $maintenanceProfit;
        $totalOperatingExpenses = $expenses + $salaries;
        $formulaRevenue = $salesRevenue;
        $operatingProfitBeforeExpenses = $salesRevenue - $cogs + $maintenanceProfit;

        return [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'revenue' => [
                'sales_net_revenue' => $salesRevenue,
                'sales_gross_revenue' => $salesSummary['total_revenue'],
                'sales_returns' => $salesSummary['total_returns'],
                'maintenance_revenue' => $maintenanceRevenue,
                'formula_revenue' => round($formulaRevenue, 2),
            ],
            'cost_of_goods_sold' => [
                'sales_cogs' => $cogs,
                'gross_profit' => round($grossProfit, 2),
                'gross_margin_percentage' => $salesRevenue > 0
                    ? round(($grossProfit / $salesRevenue) * 100, 2)
                    : 0,
            ],
            'maintenance_costs' => [
                'parts_cost' => $maintenanceSummary['total_parts_cost'],
                'maintenance_profit' => $maintenanceProfit,
            ],
            'operating_expenses' => [
                'basis' => 'cash',
                'expenses' => $expenses,
                'salaries' => $salaries,
                'total_operating_expenses' => round($totalOperatingExpenses, 2),
            ],
            'profit_formula' => [
                'sales_revenue' => $salesRevenue,
                'minus_cogs' => $cogs,
                'minus_expenses' => $expenses,
                'minus_salaries' => $salaries,
                'plus_maintenance_profit' => $maintenanceProfit,
                'operating_profit_before_expenses' => round($operatingProfitBeforeExpenses, 2),
            ],
            'purchases' => [
                'total_purchases' => $purchaseSummary['total_purchase_amount'],
                'purchase_returns' => $purchaseSummary['total_return_refund'],
                'net_purchases' => $purchaseSummary['net_purchase_amount'],
            ],
            'net_profit' => round($netProfit, 2),
            'profit_margin_percentage' => $formulaRevenue > 0
                ? round(($netProfit / $formulaRevenue) * 100, 2)
                : 0,
        ];
    }

    private function getSalarySummary(Carbon $dateFrom, Carbon $dateTo): array
    {
        $salaries = DB::table('salary_payments')
            ->where('status', 'confirmed')
            ->where('payment_date', '>=', $dateFrom->toDateString())
            ->where('payment_date', '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) as total_confirmed,
                COUNT(*) as payment_count
            ')
            ->first();

        return [
            'total_confirmed' => (float) $salaries->total_confirmed,
            'payment_count' => (int) $salaries->payment_count,
        ];
    }
}
