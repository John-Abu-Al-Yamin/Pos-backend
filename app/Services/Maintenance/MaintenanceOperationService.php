<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceHeader;
use App\Models\MaintenanceOperation;
use DomainException;

class MaintenanceOperationService
{
    public function addOperation(MaintenanceHeader $header, array $data): MaintenanceOperation
    {
        if ($header->isTerminal()) {
            throw new DomainException('لا يمكن إضافة عمليات لتذكرة مكتملة أو ملغاة.');
        }

        $data['maintenance_header_id'] = $header->id;
        
        return MaintenanceOperation::create($data);
    }

    public function updateOperation(MaintenanceHeader $header, MaintenanceOperation $operation, array $data): MaintenanceOperation
    {
        if ($operation->maintenance_header_id !== $header->id) {
            throw new DomainException('عملية الصيانة غير تابعة لهذه التذكرة.');
        }

        if ($header->isTerminal()) {
            throw new DomainException('لا يمكن تعديل عمليات لتذكرة مكتملة أو ملغاة.');
        }

        $operation->update($data);

        return $operation->fresh();
    }

    public function removeOperation(MaintenanceHeader $header, MaintenanceOperation $operation): void
    {
        if ($operation->maintenance_header_id !== $header->id) {
            throw new DomainException('عملية الصيانة غير تابعة لهذه التذكرة.');
        }

        if ($header->isTerminal()) {
            throw new DomainException('لا يمكن حذف عمليات لتذكرة مكتملة أو ملغاة.');
        }

        $operation->delete();
    }
}
