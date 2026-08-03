<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['module', 'occurred_at'], 'audit_logs_module_occurred_idx');
            $table->index(['action', 'occurred_at'], 'audit_logs_action_occurred_idx');
            $table->index(['status', 'occurred_at'], 'audit_logs_status_occurred_idx');
            $table->index(['severity', 'occurred_at'], 'audit_logs_severity_occurred_idx');
            $table->index(['ip_address', 'occurred_at'], 'audit_logs_ip_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_module_occurred_idx');
            $table->dropIndex('audit_logs_action_occurred_idx');
            $table->dropIndex('audit_logs_status_occurred_idx');
            $table->dropIndex('audit_logs_severity_occurred_idx');
            $table->dropIndex('audit_logs_ip_occurred_idx');
        });
    }
};
