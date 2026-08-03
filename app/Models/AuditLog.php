<?php

namespace App\Models;

use App\Models\Builders\AuditLogBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class AuditLog extends Model
{
    private static bool $allowMutation = false;

    protected $fillable = [
        'event_uuid',
        'batch_uuid',
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'module',
        'action',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'device',
        'platform',
        'browser',
        'method',
        'route',
        'url',
        'request_id',
        'status',
        'severity',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            if (! self::$allowMutation) {
                throw new RuntimeException('Audit logs are append-only and cannot be updated.');
            }
        });

        static::deleting(function (): void {
            if (! self::$allowMutation) {
                throw new RuntimeException('Audit logs are append-only and cannot be deleted.');
            }
        });
    }

    public static function withoutMutationGuard(callable $callback): mixed
    {
        $previous = self::$allowMutation;
        self::$allowMutation = true;

        try {
            return $callback();
        } finally {
            self::$allowMutation = $previous;
        }
    }

    public static function mutationsAllowed(): bool
    {
        return self::$allowMutation;
    }

    public function newEloquentBuilder($query): Builder
    {
        return new AuditLogBuilder($query);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
