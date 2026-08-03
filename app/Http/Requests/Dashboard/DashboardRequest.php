<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Report\Concerns\ResolvesReportPeriod;
use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    use ResolvesReportPeriod;

    public function authorize(): bool
    {
        return $this->user()?->can('view-dashboard') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('period') && !$this->filled('date_from') && !$this->filled('date_to')) {
            $this->merge(['period' => 'today']);
        }

        $this->normalizeReportPeriod();
    }

    protected function resolvePeriodDates(string $period): array
    {
        $today = now();

        return match ($period) {
            'today' => [
                $today->copy()->toDateString(),
                $today->copy()->toDateString(),
            ],
            'this_week' => [
                $today->copy()->subDays(6)->toDateString(),
                $today->copy()->toDateString(),
            ],
            default => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->toDateString(),
            ],
        };
    }

    public function rules(): array
    {
        return [
            'period' => 'nullable|in:today,this_week,this_month,custom',
            'date_from' => 'required_if:period,custom|date_format:Y-m-d',
            'date_to' => 'required_if:period,custom|date_format:Y-m-d|after_or_equal:date_from',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('period') !== 'custom') {
                return;
            }

            if (!$this->input('date_from')) {
                $validator->errors()->add('date_from', 'Start date is required when period is custom');
            }

            if (!$this->input('date_to')) {
                $validator->errors()->add('date_to', 'End date is required when period is custom');
            }

            if ($this->input('date_from') && $this->input('date_to')) {
                $from = \Carbon\Carbon::parse($this->input('date_from'));
                $to = \Carbon\Carbon::parse($this->input('date_to'));
                $diffInDays = $from->diffInDays($to);

                if ($diffInDays > 90) {
                    $validator->errors()->add(
                        'date_to',
                        'Custom date range cannot exceed 90 days. Selected range is ' . $diffInDays . ' days.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'period.in' => 'Period must be one of: today, this_week, this_month, custom',
            'date_from.required_if' => 'Start date is required when period is custom',
            'date_to.required_if' => 'End date is required when period is custom',
            'date_from.date_format' => 'Start date must be in Y-m-d format',
            'date_to.date_format' => 'End date must be in Y-m-d format',
            'date_to.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
