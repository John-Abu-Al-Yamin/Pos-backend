<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days= : Override the configured retention window in days} {--force : Required for pruning in production}';

    protected $description = 'Delete audit logs older than the configured retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('audit.retention_days', 1095));

        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('The --force option is required to prune audit logs in production.');

            return self::FAILURE;
        }

        if ($days < 1) {
            $this->error('Retention days must be greater than zero.');

            return self::FAILURE;
        }

        $deleted = AuditLog::withoutMutationGuard(fn () => AuditLog::query()
            ->where('occurred_at', '<', now()->subDays($days))
            ->delete());

        $this->info("Deleted {$deleted} audit log records older than {$days} days.");

        return self::SUCCESS;
    }
}
