<?php

namespace App\Models\Builders;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class AuditLogBuilder extends Builder
{
    public function update(array $values): int
    {
        if (! AuditLog::mutationsAllowed()) {
            throw new RuntimeException('Audit logs are append-only and cannot be updated.');
        }

        return parent::update($values);
    }

    public function delete(): mixed
    {
        if (! AuditLog::mutationsAllowed()) {
            throw new RuntimeException('Audit logs are append-only and cannot be deleted.');
        }

        return parent::delete();
    }
}
