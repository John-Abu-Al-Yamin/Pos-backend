<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryReportService
{
    public function generate(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
        $status = $filters['status'] ?? null;
        $userId = $filters['created_by'] ?? null;

        return [
            'basis' => [
                'date_column' => 'payment_date',
                'fallback_date_column' => 'created_at للدفعات المسودة والملغية التي لا تحتوي على تاريخ دفع',
                'description' => 'يتم احتساب مصروف الرواتب المؤكد باستخدام تاريخ الدفع (payment_date)؛ وتشمل تقارير الحالة سجلات المسودة والملغية عبر تاريخ الإنشاء (created_at) عندما لا يكون تاريخ الدفع محدداً.',
                'supported_statuses' => ['مسودة', 'مؤكد', 'ملغي'],
            ],
            'summary' => $this->getSummary($dateFrom, $dateTo, $status, $userId),
            'by_employee' => $this->getByEmployee($dateFrom, $dateTo, $status, $userId),
            'by_period' => $this->getByPeriod($dateFrom, $dateTo, $filters['group_by'] ?? 'month', $status, $userId),
            'by_status' => $this->getByStatus($dateFrom, $dateTo, $userId),
            'by_item_type' => $this->getByItemType($dateFrom, $dateTo, $status, $userId),
        ];
    }

    public function getSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $status = null,
        ?int $userId = null,
    ): array {
        $query = $this->basePaymentQuery($dateFrom, $dateTo, $status, $userId);

        $summary = $query->selectRaw('
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END), 0) as confirmed_amount,
            COALESCE(SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END), 0) as draft_amount,
            COALESCE(SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END), 0) as cancelled_amount,
            0 as paid_amount,
            COUNT(*) as payment_count
        ', ['confirmed', 'draft', 'cancelled'])->first();

        return [
            'total_amount' => (float) $summary->total_amount,
            'confirmed_amount' => (float) $summary->confirmed_amount,
            'draft_amount' => (float) $summary->draft_amount,
            'cancelled_amount' => (float) $summary->cancelled_amount,
            'paid_amount' => (float) $summary->paid_amount,
            'payment_count' => (int) $summary->payment_count,
        ];
    }

    public function getOperationalSummary(): array
    {
        return [
            'draft_count' => (int) DB::table('salary_payments')
                ->where('status', 'draft')
                ->count(),
        ];
    }

    private function getByEmployee(Carbon $dateFrom, Carbon $dateTo, ?string $status, ?int $userId): array
    {
        return $this->basePaymentQuery($dateFrom, $dateTo, $status, $userId)
            ->join('users', 'users.id', '=', 'salary_payments.user_id')
            ->selectRaw('
                users.id as employee_id,
                users.name as employee_name,
                COALESCE(SUM(salary_payments.total_amount), 0) as total_amount,
                COUNT(*) as payment_count,
                MIN(salary_payments.period_start) as first_period_start,
                MAX(salary_payments.period_end) as last_period_end
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($item) => [
                'employee_id' => (int) $item->employee_id,
                'employee_name' => $item->employee_name,
                'total_amount' => (float) $item->total_amount,
                'payment_count' => (int) $item->payment_count,
                'first_period_start' => $item->first_period_start,
                'last_period_end' => $item->last_period_end,
            ])->toArray();
    }

    private function getByPeriod(Carbon $dateFrom, Carbon $dateTo, string $groupBy, ?string $status, ?int $userId): array
    {
        $dateFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%x-%v',
            'year' => '%Y',
            default => '%Y-%m',
        };

        return $this->basePaymentQuery($dateFrom, $dateTo, $status, $userId)
            ->selectRaw("
                DATE_FORMAT(COALESCE(salary_payments.payment_date, salary_payments.created_at), '$dateFormat') as period,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COUNT(*) as payment_count
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($item) => [
                'period' => $item->period,
                'total_amount' => (float) $item->total_amount,
                'payment_count' => (int) $item->payment_count,
            ])->toArray();
    }

    private function getByStatus(Carbon $dateFrom, Carbon $dateTo, ?int $userId): array
    {
        return $this->basePaymentQuery($dateFrom, $dateTo, null, $userId)
            ->selectRaw('
                status,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COUNT(*) as payment_count
            ')
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'status' => $item->status,
                'total_amount' => (float) $item->total_amount,
                'payment_count' => (int) $item->payment_count,
            ])->toArray();
    }

    private function getByItemType(Carbon $dateFrom, Carbon $dateTo, ?string $status, ?int $userId): array
    {
        $query = $this->basePaymentQuery($dateFrom, $dateTo, $status, $userId)
            ->join('salary_payment_items', 'salary_payment_items.salary_payment_id', '=', 'salary_payments.id')
            ->selectRaw('
                salary_payment_items.type,
                COALESCE(SUM(salary_payment_items.amount), 0) as total_amount,
                COUNT(*) as item_count
            ')
            ->groupBy('salary_payment_items.type')
            ->orderByDesc('total_amount');

        return $query->get()->map(fn ($item) => [
            'type' => $item->type,
            'total_amount' => (float) $item->total_amount,
            'item_count' => (int) $item->item_count,
        ])->toArray();
    }

    private function basePaymentQuery(Carbon $dateFrom, Carbon $dateTo, ?string $status = null, ?int $userId = null)
    {
        $query = DB::table('salary_payments')
            ->where(DB::raw('COALESCE(salary_payments.payment_date, salary_payments.created_at)'), '>=', $dateFrom->toDateString())
            ->where(DB::raw('COALESCE(salary_payments.payment_date, salary_payments.created_at)'), '<', $dateTo->copy()->addDay()->toDateString());

        if ($status) {
            $query->where('status', $status);
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query;
    }
}
