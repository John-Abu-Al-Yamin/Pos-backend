<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\FinancialLedger;
use App\Models\PurchaseHeader;
use App\Models\Repair;
use App\Models\Returns;
use App\Models\Sale;
use Illuminate\Database\Seeder;

class FinancialLedgerSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSalePayments();
        $this->seedCogs();
        $this->seedReturnTransactions();
        $this->seedRepairTransactions();
        $this->seedExpensePayments();
        $this->seedPurchasePayments();
    }

    private function seedSalePayments(): void
    {
        $sales = Sale::whereNotNull('payment_received_at')
            ->where('payment_method', '!=', 'installment')
            ->get();

        foreach ($sales as $sale) {
            FinancialLedger::create([
                'event_type' => 'sale_payment',
                'account_code' => '4000',
                'amount' => (float) $sale->total,
                'direction' => 'inflow',
                'occurred_at' => $sale->payment_received_at,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'description' => "Sale #{$sale->reference_code} - {$sale->payment_method} payment",
            ]);
        }
    }

    private function seedCogs(): void
    {
        $sales = Sale::all();

        foreach ($sales as $sale) {
            $totalCost = (float) $sale->saleItems()->sum('total_cost');

            if ($totalCost > 0) {
                FinancialLedger::create([
                    'event_type' => 'cogs',
                    'account_code' => '5000',
                    'amount' => $totalCost,
                    'direction' => 'outflow',
                    'occurred_at' => $sale->payment_received_at ?? $sale->created_at,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'description' => "COGS for Sale #{$sale->reference_code}",
                ]);
            }
        }
    }

    private function seedReturnTransactions(): void
    {
        $returns = Returns::whereNotNull('refund_processed_at')->get();

        foreach ($returns as $return) {
            FinancialLedger::create([
                'event_type' => 'refund_disbursement',
                'account_code' => '6100',
                'amount' => (float) $return->refund_total,
                'direction' => 'outflow',
                'occurred_at' => $return->refund_processed_at,
                'reference_type' => Returns::class,
                'reference_id' => $return->id,
                'description' => "Refund #{$return->reference_code} disbursement",
            ]);

            $cogsReversal = (float) $return->returnItems()->sum('total_cost');

            if ($cogsReversal > 0) {
                FinancialLedger::create([
                    'event_type' => 'cogs_reversal',
                    'account_code' => '5010',
                    'amount' => $cogsReversal,
                    'direction' => 'inflow',
                    'occurred_at' => $return->refund_processed_at,
                    'reference_type' => Returns::class,
                    'reference_id' => $return->id,
                    'description' => "COGS reversal for Return #{$return->reference_code}",
                ]);
            }

            if ((float) $return->restocking_fee > 0) {
                FinancialLedger::create([
                    'event_type' => 'restocking_fee',
                    'account_code' => '4020',
                    'amount' => (float) $return->restocking_fee,
                    'direction' => 'inflow',
                    'occurred_at' => $return->refund_processed_at,
                    'reference_type' => Returns::class,
                    'reference_id' => $return->id,
                    'description' => "Restocking fee for Return #{$return->reference_code}",
                ]);
            }
        }
    }

    private function seedRepairTransactions(): void
    {
        $repairs = Repair::all();

        foreach ($repairs as $repair) {
            if ((float) $repair->deposit > 0 && $repair->deposit_paid_at) {
                FinancialLedger::create([
                    'event_type' => 'repair_deposit',
                    'account_code' => '4010',
                    'amount' => (float) $repair->deposit,
                    'direction' => 'inflow',
                    'occurred_at' => $repair->deposit_paid_at,
                    'reference_type' => Repair::class,
                    'reference_id' => $repair->id,
                    'description' => "Repair #{$repair->reference_code} deposit",
                ]);
            }

            if ((float) $repair->final_payment > 0 && $repair->final_paid_at) {
                FinancialLedger::create([
                    'event_type' => 'repair_final_payment',
                    'account_code' => '4010',
                    'amount' => (float) $repair->final_payment,
                    'direction' => 'inflow',
                    'occurred_at' => $repair->final_paid_at,
                    'reference_type' => Repair::class,
                    'reference_id' => $repair->id,
                    'description' => "Repair #{$repair->reference_code} final payment",
                ]);
            }

            $partsCost = (float) ($repair->final_parts_cost ?? $repair->parts_cost);

            if ($partsCost > 0) {
                FinancialLedger::create([
                    'event_type' => 'repair_parts_consumption',
                    'account_code' => '5020',
                    'amount' => $partsCost,
                    'direction' => 'outflow',
                    'occurred_at' => $repair->completed_at ?? $repair->created_at,
                    'reference_type' => Repair::class,
                    'reference_id' => $repair->id,
                    'description' => "Repair #{$repair->reference_code} parts consumed",
                ]);
            }
        }
    }

    private function seedExpensePayments(): void
    {
        $expenses = Expense::all();

        foreach ($expenses as $expense) {
            FinancialLedger::create([
                'event_type' => 'expense_payment',
                'account_code' => '6000',
                'amount' => (float) $expense->amount,
                'direction' => 'outflow',
                'occurred_at' => $expense->expense_date ?? $expense->created_at,
                'reference_type' => Expense::class,
                'reference_id' => $expense->id,
                'description' => "Expense: {$expense->title}",
            ]);
        }
    }

    private function seedPurchasePayments(): void
    {
        $purchases = PurchaseHeader::all();

        foreach ($purchases as $purchase) {
            if ((float) $purchase->total <= 0) {
                continue;
            }

            FinancialLedger::create([
                'event_type' => 'purchase_payment',
                'account_code' => '7000',
                'amount' => (float) $purchase->total,
                'direction' => 'outflow',
                'occurred_at' => $purchase->created_at,
                'reference_type' => PurchaseHeader::class,
                'reference_id' => $purchase->id,
                'description' => "Purchase #{$purchase->reference_code} payment",
            ]);
        }
    }
}
