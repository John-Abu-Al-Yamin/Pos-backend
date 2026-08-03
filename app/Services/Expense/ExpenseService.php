<?php

namespace App\Services\Expense;

use App\Models\Expense;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function pay(Expense $expense): Expense
    {
        $paidExpense = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($expense) {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            if (! $expense->isPending()) {
                throw new \DomainException('لا يمكن دفع مصروف غير معلق.');
            }

            $expense->update([
                'status' => 'paid',
                'payment_date' => now()->toDateString(),
            ]);

            return $expense->fresh();
        }));

        app(AuditLogService::class)->record(
            module: 'expenses',
            action: 'expense_paid',
            auditable: $paidExpense,
            metadata: [
                'reference_number' => 'EXP-'.$paidExpense->id,
                'expense_category' => $paidExpense->expense_category ?? null,
                'expense_category_id' => $paidExpense->expense_category_id ?? null,
                'amount' => (float) $paidExpense->amount,
                'payment_date' => optional($paidExpense->payment_date)->toDateString(),
            ],
            severity: 'critical',
        );

        return $paidExpense;
    }

    public function cancel(Expense $expense): Expense
    {
        $cancelledExpense = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($expense) {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            if ($expense->isCancelled()) {
                throw new \DomainException('المصروف ملغي بالفعل.');
            }

            $expense->update([
                'status' => 'cancelled',
            ]);

            return $expense->fresh();
        }));

        app(AuditLogService::class)->record(
            module: 'expenses',
            action: 'expense_cancelled',
            auditable: $cancelledExpense,
            metadata: [
                'reference_number' => 'EXP-'.$cancelledExpense->id,
                'expense_category' => $cancelledExpense->expense_category ?? null,
                'expense_category_id' => $cancelledExpense->expense_category_id ?? null,
                'amount' => (float) $cancelledExpense->amount,
                'reason' => $cancelledExpense->notes,
            ],
            severity: 'warning',
        );

        return $cancelledExpense;
    }
}
