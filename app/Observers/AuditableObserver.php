<?php

namespace App\Observers;

use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function created(Model $model): void
    {
        $this->auditLogService->recordModelEvent('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->auditLogService->recordModelEvent('updated', $model);
    }

    public function deleted(Model $model): void
    {
        $this->auditLogService->recordModelEvent('deleted', $model);
    }

    public function restored(Model $model): void
    {
        $this->auditLogService->recordModelEvent('restored', $model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->auditLogService->recordModelEvent('force_deleted', $model);
    }
}
