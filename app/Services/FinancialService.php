<?php

namespace App\Services;

use App\Models\FinancialLedger;

class FinancialService
{
    private const REVENUE_EVENTS = ['sale_payment', 'repair_deposit', 'repair_final_payment', 'restocking_fee', 'inventory_gain'];
    private const REVENUE_CONTRA_EVENTS = ['sale_void', 'refund_disbursement', 'repair_deposit_refund', 'repair_void', 'inventory_adjustment_void'];
    private const EXPENSE_EVENTS = ['cogs', 'repair_parts_consumption', 'inventory_loss', 'expense_payment'];
    private const EXPENSE_CONTRA_EVENTS = ['cogs_reversal', 'expense_void'];
    private const PURCHASE_EVENTS = ['purchase_payment'];
    private const PURCHASE_CONTRA_EVENTS = ['purchase_void'];

    public function __construct(
        private readonly FinancialLedgerService $ledger,
    ) {}

    public function getMetrics(?string $from, ?string $to): array
    {
        $revenue = $this->sumByType(self::REVENUE_EVENTS, 'inflow', $from, $to)
            - $this->sumByType(self::REVENUE_CONTRA_EVENTS, 'outflow', $from, $to);

        $expenses = $this->sumByType(self::EXPENSE_EVENTS, 'outflow', $from, $to)
            - $this->sumByType(self::EXPENSE_CONTRA_EVENTS, 'inflow', $from, $to);

        $totalPurchases = $this->sumByType(self::PURCHASE_EVENTS, 'outflow', $from, $to)
            - $this->sumByType(self::PURCHASE_CONTRA_EVENTS, 'inflow', $from, $to);

        $totalSalesInflow = $this->sumByType(['sale_payment'], 'inflow', $from, $to);
        $totalSalesVoid = $this->sumByType(['sale_void'], 'outflow', $from, $to);
        $totalSales = $totalSalesInflow - $totalSalesVoid;

        $totalRefunds = $this->sumByType(['refund_disbursement'], 'outflow', $from, $to);

        $cogs = $this->sumByType(['cogs'], 'outflow', $from, $to);
        $cogsReversal = $this->sumByType(['cogs_reversal'], 'inflow', $from, $to);

        $restockingFeeIncome = $this->sumByType(['restocking_fee'], 'inflow', $from, $to);

        $repairRevenue = $this->sumByType(['repair_deposit', 'repair_final_payment'], 'inflow', $from, $to);
        $repairContra = $this->sumByType(['repair_deposit_refund', 'repair_void'], 'outflow', $from, $to);
        $repairRevenueNet = $repairRevenue - $repairContra;

        $repairExpenses = $this->sumByType(['repair_parts_consumption'], 'outflow', $from, $to);

        $inventoryLoss = $this->sumByType(['inventory_loss'], 'outflow', $from, $to)
            - $this->sumByEventTypeAndDirection('inventory_adjustment_void', 'inflow', $from, $to);
        $inventoryGain = $this->sumByType(['inventory_gain'], 'inflow', $from, $to)
            - $this->sumByEventTypeAndDirection('inventory_adjustment_void', 'outflow', $from, $to);

        $grossProfit = (float) ($totalSales - $totalRefunds - $cogs + $cogsReversal + $restockingFeeIncome);
        $repairProfit = (float) ($repairRevenueNet - $repairExpenses);

        return [
            'totalPurchases' => (float) $totalPurchases,
            'totalSales' => (float) $totalSales,
            'totalRefunds' => (float) $totalRefunds,
            'cogs' => (float) $cogs,
            'cogsReversal' => (float) $cogsReversal,
            'restockingFeeIncome' => (float) $restockingFeeIncome,
            'cashFlow' => $this->ledger->cashFlow($from, $to),
            'grossProfit' => $grossProfit,
            'totalExpenses' => (float) $expenses,
            'netProfit' => (float) ($grossProfit + $repairProfit - $expenses - $inventoryLoss + $inventoryGain),
            'repairRevenue' => (float) $repairRevenueNet,
            'repairExpenses' => (float) $repairExpenses,
            'repairProfit' => $repairProfit,
            'inventoryLoss' => (float) $inventoryLoss,
            'inventoryGain' => (float) $inventoryGain,
        ];
    }

    private function sumByType(array $eventTypes, string $direction, ?string $from, ?string $to): float
    {
        return (float) FinancialLedger::whereIn('event_type', $eventTypes)
            ->where('direction', $direction)
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to . ' 23:59:59'))
            ->sum('amount');
    }

    private function sumByEventTypeAndDirection(string $eventType, string $direction, ?string $from, ?string $to): float
    {
        return (float) FinancialLedger::where('event_type', $eventType)
            ->where('direction', $direction)
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to . ' 23:59:59'))
            ->sum('amount');
    }
}
