<?php

namespace App\Http\Requests\Report\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

trait ResolvesReportPeriod
{
    protected function normalizeReportPeriod(): void
    {
        $period = $this->input('period');

        if (!$period) {
            $period = ($this->filled('date_from') || $this->filled('date_to'))
                ? 'custom'
                : 'this_month';
        }

        if ($period === 'custom') {
            $this->merge(['period' => $period]);
            return;
        }

        [$dateFrom, $dateTo] = $this->resolvePeriodDates($period);

        $this->merge([
            'period' => $period,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    protected function resolvePeriodDates(string $period): array
    {
        $today = now();

        return match ($period) {
            'today' => [
                $today->copy()->toDateString(),
                $today->copy()->toDateString(),
            ],
            'yesterday' => [
                $today->copy()->subDay()->toDateString(),
                $today->copy()->subDay()->toDateString(),
            ],
            'this_week' => [
                $today->copy()->startOfWeek()->toDateString(),
                $today->copy()->endOfWeek()->toDateString(),
            ],
            'last_week' => [
                $today->copy()->subWeek()->startOfWeek()->toDateString(),
                $today->copy()->subWeek()->endOfWeek()->toDateString(),
            ],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                $today->copy()->startOfYear()->toDateString(),
                $today->copy()->endOfYear()->toDateString(),
            ],
            'all_time' => [
                '1900-01-01',
                $today->copy()->toDateString(),
            ],
            default => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ],
        };
    }

    public function resolvedPeriod(): array
    {
        return [
            'period' => $this->input('period', 'this_month'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
        ];
    }

    protected function periodValidationRule(): string
    {
        return 'nullable|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,all_time,custom';
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status' => 422,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
