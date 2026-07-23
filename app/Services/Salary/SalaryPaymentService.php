<?php

namespace App\Services\Salary;

use App\Models\SalaryAssignment;
use App\Models\SalaryPayment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class SalaryPaymentService
{
    public function createPayment(array $data): SalaryPayment
    {
        return DB::transaction(function () use ($data) {
            $user = User::findOrFail($data['user_id']);

            $existingDraft = SalaryPayment::forUser($user->id)
                ->where('status', 'draft')
                ->exists();

            if ($existingDraft) {
                throw new DomainException('يوجد بالفعل دفعة راتب مسودة لهذا المستخدم.');
            }

            $assignment = SalaryAssignment::forUser($user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$assignment) {
                throw new DomainException('لا يوجد تخصيص راتب نشط لهذا المستخدم.');
            }

            $paymentNumber = $this->generatePaymentNumber();

            $payment = SalaryPayment::create([
                'user_id' => $user->id,
                'salary_assignment_id' => $assignment->id,
                'payment_number' => $paymentNumber,
                'total_amount' => 0,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->createBaseSalaryItem($payment, $assignment);

            $payment->fresh()->recalculateTotal();

            return $payment->fresh();
        });
    }

    private function createBaseSalaryItem(SalaryPayment $payment, SalaryAssignment $assignment): void
    {
        if ($assignment->base_salary === null || (float) $assignment->base_salary <= 0) {
            return;
        }

        $payment->items()->create([
            'type' => 'base_salary',
            'label' => 'Base Salary',
            'amount' => (float) $assignment->base_salary,
        ]);
    }

    public function confirmPayment(SalaryPayment $payment): SalaryPayment
    {
        return DB::transaction(function () use ($payment) {
            if (!$payment->isDraft()) {
                throw new DomainException('يمكن تأكيد المدفوعات المسودة فقط.');
            }

            $fresh = $payment->fresh();
            $fresh->load(['items' => function ($query) {
                $query->lockForUpdate();
            }]);

            if ($fresh->items->isEmpty()) {
                throw new DomainException('لا يمكن تأكيد دفعة راتب بدون بنود.');
            }

            $fresh->recalculateTotal();

            $fresh->update([
                'status' => 'confirmed',
                'payment_date' => now(),
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);

            return $fresh->fresh();
        });
    }

    public function cancelPayment(SalaryPayment $payment): SalaryPayment
    {
        if (!$payment->isDraft()) {
            throw new DomainException('يمكن إلغاء المدفوعات المسودة فقط.');
        }

        $payment->update(['status' => 'cancelled']);

        return $payment->fresh();
    }

    private function generatePaymentNumber(): string
    {
        $lock = DB::table('salary_payments')
            ->selectRaw('MAX(id) as last_id')
            ->lockForUpdate()
            ->first();

        $lastId = $lock?->last_id ?? 0;

        $next = $lastId + 1;

        return 'SAL-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
