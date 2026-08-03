<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\InventoryQuantity;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditLogService
{
    private static bool $recording = false;

    private static bool $modelEventsSuppressed = false;

    private static ?bool $auditTableExists = null;

    public static function withoutModelEvents(callable $callback): mixed
    {
        $previous = self::$modelEventsSuppressed;
        self::$modelEventsSuppressed = true;

        try {
            return $callback();
        } finally {
            self::$modelEventsSuppressed = $previous;
        }
    }

    public static function forgetAuditTableExistence(): void
    {
        self::$auditTableExists = null;
    }

    public function recordModelEvent(string $action, Model $model): ?AuditLog
    {
        if (! $this->shouldRecord($model)) {
            return null;
        }

        if ($this->hasSpecialModelEvent($action, $model)) {
            return $this->recordSpecialModelEvent($action, $model);
        }

        [$oldValues, $newValues, $changedFields] = $this->changesFor($action, $model);

        if ($action === 'updated' && empty($changedFields)) {
            return null;
        }

        return $this->record(
            module: $this->moduleFor($model),
            action: $action,
            auditable: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            changedFields: $changedFields,
            metadata: [
                'model' => $model::class,
            ],
            deferUntilCommit: true,
        );
    }

    private function recordSpecialModelEvent(string $action, Model $model): ?AuditLog
    {
        if ($action === 'updated' && $model instanceof User && array_key_exists('role', $model->getChanges())) {
            return $this->record(
                module: 'users_roles',
                action: 'role_changed',
                auditable: $model,
                oldValues: ['role' => $model->getOriginal('role')],
                newValues: ['role' => $model->getAttribute('role')],
                changedFields: ['role'],
                metadata: [
                    'affected_user_id' => $model->id,
                    'affected_user_email' => $model->email,
                ],
                severity: 'critical',
                deferUntilCommit: true,
            );
        }

        if ($action === 'updated' && $model instanceof InventoryQuantity && array_key_exists('cost_price', $model->getChanges())) {
            return $this->record(
                module: 'products',
                action: 'product_cost_changed',
                auditable: $model,
                oldValues: ['cost_price' => $model->getOriginal('cost_price')],
                newValues: ['cost_price' => $model->getAttribute('cost_price')],
                changedFields: ['cost_price'],
                metadata: [
                    'product_id' => $model->product_id,
                ],
                severity: 'warning',
                deferUntilCommit: true,
            );
        }

        if ($action === 'created' && $model instanceof StockMovement && $model->movement_type === 'stock_adjustment') {
            return $this->record(
                module: 'inventory',
                action: 'stock_correction',
                auditable: $model,
                newValues: $this->snapshot($model),
                changedFields: array_keys($this->snapshot($model)),
                metadata: [
                    'product_id' => $model->product_id,
                    'inventory_item_id' => $model->inventory_item_id,
                    'movement' => $model->movement,
                    'quantity' => (float) $model->quantity,
                    'reason' => $model->notes,
                ],
                severity: 'critical',
                deferUntilCommit: true,
            );
        }

        return null;
    }

    private function hasSpecialModelEvent(string $action, Model $model): bool
    {
        return ($action === 'updated' && $model instanceof User && array_key_exists('role', $model->getChanges()))
            || ($action === 'updated' && $model instanceof InventoryQuantity && array_key_exists('cost_price', $model->getChanges()))
            || ($action === 'created' && $model instanceof StockMovement && $model->movement_type === 'stock_adjustment');
    }

    public function record(
        string $module,
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $changedFields = null,
        array $metadata = [],
        string $status = 'success',
        string $severity = 'info',
        ?Request $request = null,
        bool $deferUntilCommit = false,
    ): ?AuditLog {
        if (! config('audit.enabled', true) || self::$recording || ! $this->auditTableExists()) {
            return null;
        }

        try {
            $payload = $this->payloadFor(
                module: $module,
                action: $action,
                auditable: $auditable,
                oldValues: $oldValues,
                newValues: $newValues,
                changedFields: $changedFields,
                metadata: $metadata,
                status: $status,
                severity: $severity,
                request: $request ?? request(),
            );

            if ($deferUntilCommit && DB::transactionLevel() > 0) {
                DB::afterCommit(fn () => $this->createAuditLog($payload));

                return null;
            }

            return $this->createAuditLog($payload);
        } catch (Throwable $e) {
            $this->logWriteFailure($module, $action, $auditable, $e);

            return null;
        }
    }

    private function payloadFor(
        string $module,
        string $action,
        ?Model $auditable,
        ?array $oldValues,
        ?array $newValues,
        ?array $changedFields,
        array $metadata,
        string $status,
        string $severity,
        Request $request,
    ): array {
        $user = Auth::user();
        $context = app(AuditContext::class)->forRequest($request);

        return array_merge($context, [
            'event_uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'module' => $module,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'auditable_label' => $auditable ? $this->labelFor($auditable) : null,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'changed_fields' => $this->sanitizeChangedFields($changedFields),
            'status' => $status,
            'severity' => $severity,
            'metadata' => $this->sanitize($metadata),
            'occurred_at' => now(),
        ]);
    }

    private function createAuditLog(array $payload): ?AuditLog
    {
        if (self::$recording) {
            return null;
        }

        self::$recording = true;

        try {
            return AuditLog::create($payload);
        } catch (Throwable $e) {
            $this->logWriteFailure(
                $payload['module'] ?? 'unknown',
                $payload['action'] ?? 'unknown',
                null,
                $e,
            );

            return null;
        } finally {
            self::$recording = false;
        }
    }

    private function shouldRecord(Model $model): bool
    {
        if (! config('audit.enabled', true) || self::$modelEventsSuppressed) {
            return false;
        }

        return $this->auditTableExists()
            && array_key_exists($model::class, config('audit.models', []));
    }

    private function changesFor(string $action, Model $model): array
    {
        return match ($action) {
            'created' => [null, $this->snapshot($model), array_keys($this->snapshot($model))],
            'deleted', 'restored', 'force_deleted' => [$this->snapshot($model), null, array_keys($this->snapshot($model))],
            'updated' => $this->updatedChanges($model),
            default => [null, null, null],
        };
    }

    private function updatedChanges(Model $model): array
    {
        $changes = Arr::except($model->getChanges(), config('audit.ignored_fields', []));
        $oldValues = [];
        $newValues = [];

        foreach ($changes as $field => $value) {
            $oldValues[$field] = $model->getOriginal($field);
            $newValues[$field] = $value;
        }

        return [$oldValues, $newValues, array_keys($changes)];
    }

    private function auditTableExists(): bool
    {
        if (self::$auditTableExists === true) {
            return true;
        }

        return self::$auditTableExists = Schema::hasTable('audit_logs');
    }

    private function snapshot(Model $model): array
    {
        return Arr::except($model->getAttributes(), config('audit.ignored_fields', []));
    }

    private function moduleFor(Model $model): string
    {
        return config('audit.models')[$model::class] ?? Str::snake(class_basename($model));
    }

    private function labelFor(Model $model): string
    {
        $attributes = $model->getAttributes();

        foreach (config('audit.label_fields', []) as $field) {
            if (array_key_exists($field, $attributes) && filled($model->getAttribute($field))) {
                return (string) $model->getAttribute($field);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $hidden = array_map('strtolower', config('audit.hidden_fields', []));

        foreach ($values as $key => $value) {
            $keyText = strtolower((string) $key);

            if (in_array($keyText, $hidden, true) || Str::contains($keyText, $hidden)) {
                $values[$key] = '[hidden]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }

    private function sanitizeChangedFields(?array $changedFields): ?array
    {
        if ($changedFields === null) {
            return null;
        }

        $hidden = array_map('strtolower', config('audit.hidden_fields', []));

        return array_values(array_filter($changedFields, function (string $field) use ($hidden) {
            $field = strtolower($field);

            return ! in_array($field, $hidden, true) && ! Str::contains($field, $hidden);
        }));
    }

    private function logWriteFailure(string $module, string $action, ?Model $auditable, Throwable $e): void
    {
        Log::warning('Audit log write failed.', [
            'module' => $module,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'error' => $e->getMessage(),
        ]);
    }
}
