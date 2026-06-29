<?php

namespace App\Services;

use App\Models\FinancialLedger;

class FinancialLedgerService
{
    private const ACCOUNT_REVENUE_SALES = '4000';
    private const ACCOUNT_REVENUE_REPAIRS = '4010';
    private const ACCOUNT_REVENUE_RESTOCKING = '4020';
    private const ACCOUNT_REVENUE_INVENTORY_GAIN = '4030';
    private const ACCOUNT_COGS = '5000';
    private const ACCOUNT_COGS_REVERSAL = '5010';
    private const ACCOUNT_REPAIR_PARTS = '5020';
    private const ACCOUNT_INVENTORY_LOSS = '5030';
    private const ACCOUNT_EXPENSES = '6000';
    private const ACCOUNT_PURCHASES = '7000';
    private const ACCOUNT_CONTRA_SALES = '4100';
    private const ACCOUNT_CONTRA_REPAIRS = '4110';
    private const ACCOUNT_CONTRA_INVENTORY = '4120';
    private const ACCOUNT_REFUNDS = '6100';

    public function recordSalePayment(object $sale): void
    {
        if ($sale->payment_method === 'installment') {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'sale_payment',
            'account_code' => self::ACCOUNT_REVENUE_SALES,
            'amount' => (float) $sale->total,
            'direction' => 'inflow',
            'occurred_at' => $sale->payment_received_at ?? $sale->created_at,
            'reference_type' => get_class($sale),
            'reference_id' => $sale->id,
            'description' => "Sale #{$sale->reference_code} - {$sale->payment_method} payment",
        ]);
    }

    public function recordCogs(object $sale, float $cogsTotal): void
    {
        if (!($cogsTotal > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'cogs',
            'account_code' => self::ACCOUNT_COGS,
            'amount' => $cogsTotal,
            'direction' => 'outflow',
            'occurred_at' => $sale->payment_received_at ?? $sale->created_at,
            'reference_type' => get_class($sale),
            'reference_id' => $sale->id,
            'description' => "COGS for Sale #{$sale->reference_code}",
        ]);
    }

    public function recordCogsReversal(object $return, float $reversalTotal): void
    {
        if (!($reversalTotal > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'cogs_reversal',
            'account_code' => self::ACCOUNT_COGS_REVERSAL,
            'amount' => $reversalTotal,
            'direction' => 'inflow',
            'occurred_at' => $return->refund_processed_at ?? $return->created_at,
            'reference_type' => get_class($return),
            'reference_id' => $return->id,
            'description' => "COGS reversal for Return #{$return->reference_code}",
        ]);
    }

    public function recordRestockingFee(object $return, float $fee): void
    {
        if (!($fee > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'restocking_fee',
            'account_code' => self::ACCOUNT_REVENUE_RESTOCKING,
            'amount' => $fee,
            'direction' => 'inflow',
            'occurred_at' => $return->refund_processed_at ?? $return->created_at,
            'reference_type' => get_class($return),
            'reference_id' => $return->id,
            'description' => "Restocking fee for Return #{$return->reference_code}",
        ]);
    }

    public function recordRepairDeposit(object $repair): void
    {
        if (!((float) $repair->deposit > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'repair_deposit',
            'account_code' => self::ACCOUNT_REVENUE_REPAIRS,
            'amount' => (float) $repair->deposit,
            'direction' => 'inflow',
            'occurred_at' => $repair->deposit_paid_at ?? $repair->created_at,
            'reference_type' => get_class($repair),
            'reference_id' => $repair->id,
            'description' => "Repair #{$repair->reference_code} deposit",
        ]);
    }

    public function recordRepairFinalPayment(object $repair, ?float $amount = null): void
    {
        $finalAmount = $amount ?? ((float) $repair->estimated_cost - (float) $repair->deposit);

        if (!($finalAmount > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'repair_final_payment',
            'account_code' => self::ACCOUNT_REVENUE_REPAIRS,
            'amount' => $finalAmount,
            'direction' => 'inflow',
            'occurred_at' => $repair->final_paid_at ?? now(),
            'reference_type' => get_class($repair),
            'reference_id' => $repair->id,
            'description' => "Repair #{$repair->reference_code} final payment",
        ]);
    }

    public function recordRepairPartsConsumption(object $repair, float $partsCost): void
    {
        if (!($partsCost > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'repair_parts_consumption',
            'account_code' => self::ACCOUNT_REPAIR_PARTS,
            'amount' => $partsCost,
            'direction' => 'outflow',
            'occurred_at' => $repair->completed_at ?? now(),
            'reference_type' => get_class($repair),
            'reference_id' => $repair->id,
            'description' => "Repair #{$repair->reference_code} parts consumed",
        ]);
    }

    public function recordRefundDisbursement(object $return): void
    {
        if (!((float) $return->refund_total > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'refund_disbursement',
            'account_code' => self::ACCOUNT_REFUNDS,
            'amount' => (float) $return->refund_total,
            'direction' => 'outflow',
            'occurred_at' => $return->refund_processed_at ?? $return->created_at,
            'reference_type' => get_class($return),
            'reference_id' => $return->id,
            'description' => "Refund #{$return->reference_code} disbursement",
        ]);
    }

    public function recordPurchasePayment(object $purchase): void
    {
        if (!((float) $purchase->total > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'purchase_payment',
            'account_code' => self::ACCOUNT_PURCHASES,
            'amount' => (float) $purchase->total,
            'direction' => 'outflow',
            'occurred_at' => $purchase->created_at,
            'reference_type' => get_class($purchase),
            'reference_id' => $purchase->id,
            'description' => "Purchase #{$purchase->reference_code} payment",
        ]);
    }

    public function recordExpensePayment(object $expense): void
    {
        if (!((float) $expense->amount > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'expense_payment',
            'account_code' => self::ACCOUNT_EXPENSES,
            'amount' => (float) $expense->amount,
            'direction' => 'outflow',
            'occurred_at' => $expense->expense_date ?? $expense->created_at,
            'reference_type' => get_class($expense),
            'reference_id' => $expense->id,
            'description' => "Expense: {$expense->title}",
        ]);
    }

    public function recordVoidReversal(object $sale, float $cogsTotal = 0): void
    {
        FinancialLedger::create([
            'event_type' => 'sale_void',
            'account_code' => self::ACCOUNT_CONTRA_SALES,
            'amount' => (float) $sale->total,
            'direction' => 'outflow',
            'occurred_at' => now(),
            'reference_type' => get_class($sale),
            'reference_id' => $sale->id,
            'description' => "Sale #{$sale->reference_code} voided — payment reversed",
        ]);

        if ($cogsTotal > 0) {
            FinancialLedger::create([
                'event_type' => 'cogs_reversal',
                'account_code' => self::ACCOUNT_COGS_REVERSAL,
                'amount' => $cogsTotal,
                'direction' => 'inflow',
                'occurred_at' => now(),
                'reference_type' => get_class($sale),
                'reference_id' => $sale->id,
                'description' => "COGS reversal for voided Sale #{$sale->reference_code}",
            ]);
        }
    }

    public function recordDepositRefund(object $repair): void
    {
        if (!((float) $repair->deposit > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'repair_deposit_refund',
            'account_code' => self::ACCOUNT_CONTRA_REPAIRS,
            'amount' => (float) $repair->deposit,
            'direction' => 'outflow',
            'occurred_at' => now(),
            'reference_type' => get_class($repair),
            'reference_id' => $repair->id,
            'description' => "Repair #{$repair->reference_code} deposit refunded on cancellation",
        ]);
    }

    public function recordRepairVoidReversal(object $repair): void
    {
        $priorEntries = FinancialLedger::where('reference_type', get_class($repair))
            ->where('reference_id', $repair->id)
            ->get();

        foreach ($priorEntries as $entry) {
            if (in_array($entry->event_type, ['repair_void', 'repair_deposit_refund'])) {
                continue;
            }

            FinancialLedger::create([
                'event_type' => 'repair_void',
                'account_code' => self::ACCOUNT_CONTRA_REPAIRS,
                'amount' => (float) $entry->amount,
                'direction' => $entry->direction === 'inflow' ? 'outflow' : 'inflow',
                'occurred_at' => now(),
                'reference_type' => get_class($repair),
                'reference_id' => $repair->id,
                'description' => "Reversal: {$entry->description}",
            ]);
        }
    }

    public function recordInventoryLoss(object $product, int $quantity, float $totalLoss): void
    {
        if (!($totalLoss > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'inventory_loss',
            'account_code' => self::ACCOUNT_INVENTORY_LOSS,
            'amount' => $totalLoss,
            'direction' => 'outflow',
            'occurred_at' => now(),
            'reference_type' => get_class($product),
            'reference_id' => $product->id,
            'description' => "Inventory loss: {$product->name} x{$quantity}",
        ]);
    }

    public function recordInventoryGain(object $product, int $quantity, float $totalGain): void
    {
        if (!($totalGain > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'inventory_gain',
            'account_code' => self::ACCOUNT_REVENUE_INVENTORY_GAIN,
            'amount' => $totalGain,
            'direction' => 'inflow',
            'occurred_at' => now(),
            'reference_type' => get_class($product),
            'reference_id' => $product->id,
            'description' => "Inventory gain: {$product->name} x{$quantity}",
        ]);
    }

    public function recordInventoryAdjustmentVoid(object $adjustment): void
    {
        $amount = (float) ($adjustment->total_loss_amount ?: $adjustment->total_gain_amount);

        if (!($amount > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'inventory_adjustment_void',
            'account_code' => self::ACCOUNT_CONTRA_INVENTORY,
            'amount' => $amount,
            'direction' => $adjustment->total_loss_amount > 0 ? 'inflow' : 'outflow',
            'occurred_at' => now(),
            'reference_type' => get_class($adjustment),
            'reference_id' => $adjustment->id,
            'description' => "Inventory adjustment #{$adjustment->id} voided — reversal",
        ]);
    }

    public function recordPurchaseVoid(object $purchase): void
    {
        FinancialLedger::create([
            'event_type' => 'purchase_void',
            'account_code' => self::ACCOUNT_PURCHASES,
            'amount' => (float) $purchase->total,
            'direction' => 'inflow',
            'occurred_at' => now(),
            'reference_type' => get_class($purchase),
            'reference_id' => $purchase->id,
            'description' => "Purchase #{$purchase->reference_code} voided — payment reversed",
        ]);
    }

    public function recordExpenseVoid(object $expense): void
    {
        FinancialLedger::create([
            'event_type' => 'expense_void',
            'account_code' => self::ACCOUNT_EXPENSES,
            'amount' => (float) $expense->amount,
            'direction' => 'inflow',
            'occurred_at' => now(),
            'reference_type' => get_class($expense),
            'reference_id' => $expense->id,
            'description' => "Expense #{$expense->id} voided — payment reversed",
        ]);
    }

    public function cashFlow(?string $from, ?string $to): float
    {
        return (float) FinancialLedger::when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to . ' 23:59:59'))
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'inflow' ? $entry->amount : -$entry->amount);
    }
}
