<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceHeader;
use DomainException;
use Illuminate\Support\Facades\DB;

class MaintenanceStatusService
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['under_repair', 'cancelled'],
        'under_repair' => ['waiting_parts', 'repaired', 'cancelled'],
        'waiting_parts' => ['under_repair', 'cancelled'],
        'repaired' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function transition(MaintenanceHeader $header, string $newStatus, ?string $deliveryDate = null, ?float $paidAmount = null): MaintenanceHeader
    {
        $currentStatus = $header->status;

        if ($currentStatus === $newStatus) {
            throw new DomainException('الحالة الجديدة مماثلة للحالة الحالية.');
        }

        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new DomainException(
                "لا يمكن تغيير الحالة من '{$currentStatus}' إلى '{$newStatus}'."
            );
        }

        return DB::transaction(function () use ($header, $newStatus, $deliveryDate, $paidAmount) {
            if ($newStatus === 'cancelled' && $header->usedParts()->exists()) {
                $partService = app(MaintenancePartService::class);
                $partService->returnAllParts($header);
            }

            $data = ['status' => $newStatus];
            
            $newAdvancePayment = $header->advance_payment;
            if ($paidAmount !== null && $paidAmount > 0) {
                $currentRemaining = max(0, (float) $header->total_cost - (float) $header->advance_payment);
                if ($paidAmount > $currentRemaining) {
                    throw new DomainException(
                        'المبلغ المدفوع يتجاوز المبلغ المطلوب. أقصى مبلغ مسموح به هو ' . number_format($currentRemaining, 2) . '.'
                    );
                }
                $newAdvancePayment += $paidAmount;
                $data['advance_payment'] = $newAdvancePayment;
            }

            if ($newStatus === 'delivered') {
                $data['delivery_date'] = $deliveryDate ?? now()->toDateString();
                
                $remaining = max(0, (float) $header->total_cost - (float) $newAdvancePayment);
                if ($remaining > 0) {
                    throw new DomainException('لا يمكن تسليم الجهاز قبل استلام كامل المبلغ المتبقي.');
                }
            }

            $header->update($data);

            return $header->fresh();
        });
    }
}
