<?php

namespace App\Services\Reports;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ExpenseReportService
{
    public function generate(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
        $category = $filters['expense_category'] ?? null;
        $status = $filters['status'] ?? null;
        $basis = $filters['expense_basis'] ?? 'accrual';
        $dateColumn = $this->dateColumnForBasis($basis);

        return [
            'basis' => [
                'type' => $basis,
                'date_column' => $dateColumn,
                'description' => $basis === 'cash'
                    ? 'الأساس النقدي يستخدم تاريخ الدفع (payment_date) ويشمل المصروفات المدفوعة في فترة الدفع.'
                    : 'الأساس الاستحقاقي يستخدم تاريخ المصروف (expense_date) ويشمل المصروفات في فترة تحملها.',
            ],
            'summary' => $this->getSummary($dateFrom, $dateTo, $category, $status, $basis),
            'by_category' => $this->getByCategory($dateFrom, $dateTo, $category, $status, $basis),
            'by_period' => $this->getByPeriod($dateFrom, $dateTo, $filters['group_by'] ?? 'day', $category, $status, $basis),
            'by_status' => $this->getByStatus($dateFrom, $dateTo, $category, $basis),
        ];
    }

    public function getSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $category = null,
        ?string $status = null,
        string $basis = 'accrual',
    ): array {
        $dateColumn = $this->dateColumnForBasis($basis);
        $baseQuery = DB::table('expenses')
            ->where('status', '!=', 'cancelled')
            ->where($dateColumn, '>=', $dateFrom->toDateString())
            ->where($dateColumn, '<', $dateTo->copy()->addDay()->toDateString());

        if ($basis === 'cash') {
            $baseQuery->where('status', 'paid');
        }

        if ($category) {
            $baseQuery->where('expense_category', $category);
        }
        if ($status) {
            $baseQuery->where('status', $status);
        }

        $total = (object) (clone $baseQuery)
            ->selectRaw('
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as total_count
            ')
            ->first();

        $paid = (object) (clone $baseQuery)
            ->where('status', 'paid')
            ->selectRaw('
                COALESCE(SUM(amount), 0) as paid_amount,
                COUNT(*) as paid_count
            ')
            ->first();

        $pending = (object) (clone $baseQuery)
            ->where('status', 'pending')
            ->selectRaw('
                COALESCE(SUM(amount), 0) as pending_amount,
                COUNT(*) as pending_count
            ')
            ->first();

        return [
            'total_amount' => (float) $total->total_amount,
            'total_count' => (int) $total->total_count,
            'paid_amount' => (float) $paid->paid_amount,
            'paid_count' => (int) $paid->paid_count,
            'pending_amount' => (float) $pending->pending_amount,
            'pending_count' => (int) $pending->pending_count,
        ];
    }

    public function getByCategory(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $category = null,
        ?string $status = null,
        string $basis = 'accrual',
    ): array {
        $dateColumn = $this->dateColumnForBasis($basis);
        $query = DB::table('expenses')
            ->where('status', '!=', 'cancelled')
            ->where($dateColumn, '>=', $dateFrom->toDateString())
            ->where($dateColumn, '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw('
                expense_category,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as count,
                COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as pending_amount
            ', ['paid', 'pending'])
            ->groupBy('expense_category')
            ->orderByDesc('total_amount');

        if ($basis === 'cash') {
            $query->where('status', 'paid');
        }

        if ($category) {
            $query->where('expense_category', $category);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return $query->get()->map(function ($item) {
            return [
                'expense_category' => $item->expense_category,
                'total_amount' => (float) $item->total_amount,
                'count' => (int) $item->count,
                'paid_amount' => (float) $item->paid_amount,
                'pending_amount' => (float) $item->pending_amount,
            ];
        })->toArray();
    }

    public function getByPeriod(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $groupBy = 'day',
        ?string $category = null,
        ?string $status = null,
        string $basis = 'accrual',
    ): array {
        $dateColumn = $this->dateColumnForBasis($basis);
        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'year' => '%Y',
            'week' => '%x-%v',
            default => '%Y-%m-%d',
        };

        $query = DB::table('expenses')
            ->where('status', '!=', 'cancelled')
            ->where($dateColumn, '>=', $dateFrom->toDateString())
            ->where($dateColumn, '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw("
                DATE_FORMAT($dateColumn, '$dateFormat') as period,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as count
            ")
            ->groupBy('period')
            ->orderBy('period');

        if ($basis === 'cash') {
            $query->where('status', 'paid');
        }

        if ($category) {
            $query->where('expense_category', $category);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return $query->get()->map(function ($item) {
            return [
                'period' => $item->period,
                'total_amount' => (float) $item->total_amount,
                'count' => (int) $item->count,
            ];
        })->toArray();
    }

    public function getByStatus(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $category = null,
        string $basis = 'accrual',
    ): array {
        $dateColumn = $this->dateColumnForBasis($basis);
        $query = DB::table('expenses')
            ->where('status', '!=', 'cancelled')
            ->where($dateColumn, '>=', $dateFrom->toDateString())
            ->where($dateColumn, '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw('
                status,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as count
            ')
            ->groupBy('status');

        if ($basis === 'cash') {
            $query->where('status', 'paid');
        }

        if ($category) {
            $query->where('expense_category', $category);
        }

        return $query->get()->map(function ($item) {
            return [
                'status' => $item->status,
                'total_amount' => (float) $item->total_amount,
                'count' => (int) $item->count,
            ];
        })->toArray();
    }

    public function getOperationalSummary(Carbon $dateFrom, Carbon $dateTo): array
    {
        $pending = DB::table('expenses')
            ->where('status', 'pending')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->first();

        return [
            'pending_count' => (int) $pending->count,
            'pending_amount' => (float) $pending->amount,
            'paid_in_period' => (int) DB::table('expenses')
                ->where('status', 'paid')
                ->where('payment_date', '>=', $dateFrom->toDateString())
                ->where('payment_date', '<', $dateTo->copy()->addDay()->toDateString())
                ->count(),
        ];
    }

    public function getRecentExpenses(int $limit = 5, bool $includeFinancials = true): array
    {
        return Expense::latest()
            ->limit($limit)
            ->get(['id', 'expense_category', 'amount', 'status', 'expense_date', 'created_at'])
            ->map(function (Expense $expense) use ($includeFinancials) {
                $item = [
                    'id' => $expense->id,
                    'expense_category' => $expense->expense_category,
                    'status' => $expense->status,
                    'expense_date' => $expense->expense_date?->toDateString(),
                    'created_at' => $expense->created_at?->toIso8601String(),
                ];

                if ($includeFinancials) {
                    $item['amount'] = (float) $expense->amount;
                }

                return $item;
            })
            ->toArray();
    }

    private function dateColumnForBasis(string $basis): string
    {
        return $basis === 'cash' ? 'payment_date' : 'expense_date';
    }
}
