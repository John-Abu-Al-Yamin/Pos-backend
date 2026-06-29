<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\PurchaseHeader;
use App\Models\Repair;
use App\Models\ReturnItem;
use App\Models\Returns;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class FinancialService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
    ) {}

    public function getMetrics(?string $from, ?string $to): array
    {
        $totalPurchases = $this->totalPurchases($from, $to);
        $totalSales = $this->totalSales($from, $to);
        $totalRefunds = $this->totalRefunds($from, $to);
        $cogs = $this->costOfGoodsSold($from, $to);
        $cogsReversal = $this->cogsReversal($from, $to);
        $restockingFeeIncome = $this->restockingFeeIncome($from, $to);

        $repairRevenue = $this->repairRevenue($from, $to);
        $repairExpenses = $this->repairPartsCost($from, $to);
        $totalExpenses = $this->totalExpenses($from, $to);

        $grossProfit = (float) ($totalSales - $totalRefunds - $cogs + $cogsReversal);
        $repairProfit = (float) ($repairRevenue - $repairExpenses);

        return [
            'totalPurchases' => (float) $totalPurchases,
            'totalSales' => (float) $totalSales,
            'totalRefunds' => (float) $totalRefunds,
            'cogs' => (float) $cogs,
            'cogsReversal' => (float) $cogsReversal,
            'restockingFeeIncome' => (float) $restockingFeeIncome,
            'cashFlow' => $this->ledger->cashFlow($from, $to),
            'grossProfit' => $grossProfit,
            'totalExpenses' => (float) $totalExpenses,
            'netProfit' => (float) ($grossProfit + $repairProfit - $totalExpenses),
            'repairRevenue' => (float) $repairRevenue,
            'repairExpenses' => (float) $repairExpenses,
            'repairProfit' => $repairProfit,
        ];
    }

    private function totalPurchases(?string $from, ?string $to): float
    {
        return PurchaseHeader::when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->sum('total');
    }

    private function totalSales(?string $from, ?string $to): float
    {
        return Sale::notVoided()
            ->where('payment_method', '!=', 'installment')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->sum('total');
    }

    private function totalRefunds(?string $from, ?string $to): float
    {
        return Returns::when($from, fn ($q) => $q->whereDate('return_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('return_date', '<=', $to))
            ->sum('refund_total');
    }

    private function costOfGoodsSold(?string $from, ?string $to): float
    {
        return (float) SaleItem::whereHas('sale', fn ($q) =>
            $q->notVoided()
              ->where('payment_method', '!=', 'installment')
              ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
              ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
        )->sum('total_cost');
    }

    private function cogsReversal(?string $from, ?string $to): float
    {
        return (float) ReturnItem::whereHas('returnHeader', fn ($q) =>
            $q->when($from, fn ($q) => $q->whereDate('return_date', '>=', $from))
              ->when($to, fn ($q) => $q->whereDate('return_date', '<=', $to))
        )->sum('total_cost');
    }

    private function restockingFeeIncome(?string $from, ?string $to): float
    {
        return (float) Returns::when($from, fn ($q) => $q->whereDate('return_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('return_date', '<=', $to))
            ->sum('restocking_fee');
    }

    private function repairRevenue(?string $from, ?string $to): float
    {
        return (float) Repair::where('status', 'completed')
            ->when($from, fn ($q) => $q->whereDate('completed_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('completed_at', '<=', $to))
            ->sum('estimated_cost');
    }

    private function repairPartsCost(?string $from, ?string $to): float
    {
        return (float) Repair::where('status', 'completed')
            ->when($from, fn ($q) => $q->whereDate('completed_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('completed_at', '<=', $to))
            ->sum('parts_cost');
    }

    private function totalExpenses(?string $from, ?string $to): float
    {
        return (float) Expense::when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->sum('amount');
    }
}
