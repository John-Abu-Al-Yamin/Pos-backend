<?php

namespace App\Services\Salary;

use App\Models\SalaryAssignment;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class SalaryPaymentService
{
    public function createPayment(array $data): SalaryPayment
    {
        $now = Carbon::now();
        $day = (int) $now->format('d');

        if ($day > 5) {
            throw new DomainException('لا يمكن إنشاء دفعة راتب بعد يوم 5 من الشهر');
        }

        $payment = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($data, $now) {
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

            if (! $assignment) {
                throw new DomainException('لا يوجد تخصيص راتب نشط لهذا المستخدم.');
            }

            $paymentNumber = $this->generatePaymentNumber();

            $periodStart = Carbon::create($now->year, $now->month, 1)->format('Y-m-d');
            $periodEnd = Carbon::create($now->year, $now->month, 1)->endOfMonth()->format('Y-m-d');

            $payment = SalaryPayment::create([
                'user_id' => $user->id,
                'salary_assignment_id' => $assignment->id,
                'payment_number' => $paymentNumber,
                'total_amount' => 0,
                'status' => 'draft',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->createBaseSalaryItem($payment, $assignment);

            $payment->fresh()->recalculateTotal();

            return $payment->fresh();
        }));

        app(AuditLogService::class)->record(
            module: 'salaries',
            action: 'salary_payment_created',
            auditable: $payment,
            metadata: [
                'reference_number' => $payment->payment_number,
                'affected_user_id' => $payment->user_id,
                'salary_assignment_id' => $payment->salary_assignment_id,
                'period_start' => optional($payment->period_start)->toDateString(),
                'period_end' => optional($payment->period_end)->toDateString(),
                'total_amount' => (float) $payment->total_amount,
            ],
        );

        return $payment;
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
        $confirmedPayment = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($payment) {
            if (! $payment->isDraft()) {
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
        }));

        app(AuditLogService::class)->record(
            module: 'salaries',
            action: 'salary_payment_confirmed',
            auditable: $confirmedPayment,
            metadata: [
                'reference_number' => $confirmedPayment->payment_number,
                'affected_user_id' => $confirmedPayment->user_id,
                'total_amount' => (float) $confirmedPayment->total_amount,
                'payment_date' => optional($confirmedPayment->payment_date)->toDateString(),
                'confirmed_by' => $confirmedPayment->confirmed_by,
            ],
            severity: 'critical',
        );

        return $confirmedPayment;
    }

    public function cancelPayment(SalaryPayment $payment): SalaryPayment
    {
        if (! $payment->isDraft()) {
            throw new DomainException('يمكن إلغاء المدفوعات المسودة فقط.');
        }

        AuditLogService::withoutModelEvents(fn () => $payment->update(['status' => 'cancelled']));

        $cancelledPayment = $payment->fresh();

        app(AuditLogService::class)->record(
            module: 'salaries',
            action: 'salary_payment_cancelled',
            auditable: $cancelledPayment,
            metadata: [
                'reference_number' => $cancelledPayment->payment_number,
                'affected_user_id' => $cancelledPayment->user_id,
                'total_amount' => (float) $cancelledPayment->total_amount,
                'reason' => $cancelledPayment->notes,
            ],
            severity: 'warning',
        );

        return $cancelledPayment;
    }

    private function generatePaymentNumber(): string
    {
        $lock = DB::table('salary_payments')
            ->selectRaw('MAX(id) as last_id')
            ->lockForUpdate()
            ->first();

        $lastId = $lock?->last_id ?? 0;

        $next = $lastId + 1;

        return 'SAL-'.str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
