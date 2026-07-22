<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceHeader;
use DomainException;

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

    public function transition(MaintenanceHeader $header, string $newStatus, ?string $deliveryDate = null): MaintenanceHeader
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

        $data = ['status' => $newStatus];

        if ($newStatus === 'delivered') {
            $data['delivery_date'] = $deliveryDate ?? now()->toDateString();
        }

        $header->update($data);

        return $header->fresh();
    }
}
