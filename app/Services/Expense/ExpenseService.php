<?php

namespace App\Services\Expense;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function pay(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense) {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            if (!$expense->isPending()) {
                throw new \DomainException('لا يمكن دفع مصروف غير معلق.');
            }

            $expense->update([
                'status' => 'paid',
                'payment_date' => now(),
            ]);

            return $expense->fresh();
        });
    }

    public function cancel(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense) {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            if ($expense->isCancelled()) {
                throw new \DomainException('المصروف ملغي بالفعل.');
            }

            $expense->update([
                'status' => 'cancelled',
            ]);

            return $expense->fresh();
        });
    }
}
