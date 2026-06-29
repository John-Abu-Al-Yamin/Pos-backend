<?php

namespace App\Services;

use App\Models\FinancialLedger;

class FinancialLedgerService
{
    public function recordSalePayment(object $sale): void
    {
        if ($sale->payment_method === 'installment') {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'sale_payment',
            'amount' => (float) $sale->total,
            'direction' => 'inflow',
            'occurred_at' => $sale->payment_received_at ?? $sale->created_at,
            'reference_type' => get_class($sale),
            'reference_id' => $sale->id,
            'description' => "Sale #{$sale->reference_code} - {$sale->payment_method} payment",
        ]);
    }

    public function recordRepairDeposit(object $repair): void
    {
        if (!((float) $repair->deposit > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'repair_deposit',
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
            'amount' => $finalAmount,
            'direction' => 'inflow',
            'occurred_at' => $repair->final_paid_at ?? now(),
            'reference_type' => get_class($repair),
            'reference_id' => $repair->id,
            'description' => "Repair #{$repair->reference_code} final payment",
        ]);
    }

    public function recordRefundDisbursement(object $return): void
    {
        if (!((float) $return->refund_total > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'refund_disbursement',
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
            'amount' => (float) $expense->amount,
            'direction' => 'outflow',
            'occurred_at' => $expense->expense_date ?? $expense->created_at,
            'reference_type' => get_class($expense),
            'reference_id' => $expense->id,
            'description' => "Expense: {$expense->title}",
        ]);
    }

    public function recordDepositRefund(object $repair): void
    {
        if (!((float) $repair->deposit > 0)) {
            return;
        }

        FinancialLedger::create([
            'event_type' => 'repair_deposit_refund',
            'amount' => (float) $repair->deposit,
            'direction' => 'outflow',
            'occurred_at' => now(),
            'reference_type' => get_class($repair),
            'reference_id' => $repair->id,
            'description' => "Repair #{$repair->reference_code} deposit refunded on cancellation",
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
