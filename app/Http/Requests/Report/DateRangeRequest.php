<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\Report\Concerns\ResolvesReportPeriod;
use Illuminate\Foundation\Http\FormRequest;

class DateRangeRequest extends FormRequest
{
    use ResolvesReportPeriod;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeReportPeriod();
    }

    public function rules(): array
    {
        return [
            'period' => $this->periodValidationRule(),
            'date_from' => 'required_if:period,custom|date_format:Y-m-d',
            'date_to' => 'required_if:period,custom|date_format:Y-m-d|after_or_equal:date_from',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'product_id' => 'nullable|exists:products,id',
            'customer_id' => 'nullable|exists:customers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'created_by' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:50',
            'expense_category' => 'nullable|string|max:50',
            'expense_basis' => 'nullable|in:accrual,cash',
            'group_by' => 'nullable|in:day,week,month,year',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $dateFrom = $this->input('date_from');
            $dateTo = $this->input('date_to');

            if ($this->input('period') === 'custom') {
                if (!$dateFrom) {
                    $validator->errors()->add('date_from', 'Start date is required when period is custom');
                }
                if (!$dateTo) {
                    $validator->errors()->add('date_to', 'End date is required when period is custom');
                }
            }

            if ($this->input('period') === 'custom' && $dateFrom && $dateTo) {
                $from = \Carbon\Carbon::parse($dateFrom);
                $to = \Carbon\Carbon::parse($dateTo);
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
            'period.in' => 'Period must be one of: today, yesterday, this_week, last_week, this_month, last_month, this_year, all_time, custom',
            'date_from.required_if' => 'Start date is required when period is custom',
            'date_to.required_if' => 'End date is required when period is custom',
            'date_from.date_format' => 'Start date must be in Y-m-d format',
            'date_to.date_format' => 'End date must be in Y-m-d format',
            'date_to.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
