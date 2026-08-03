<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\Report\Concerns\ResolvesReportPeriod;
use Illuminate\Foundation\Http\FormRequest;

class InventoryReportRequest extends FormRequest
{
    use ResolvesReportPeriod;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeReportPeriod();

        if (!$this->filled('movement_date_from')) {
            $this->merge(['movement_date_from' => $this->input('date_from')]);
        }

        if (!$this->filled('movement_date_to')) {
            $this->merge(['movement_date_to' => $this->input('date_to')]);
        }
    }

    public function rules(): array
    {
        return [
            'period' => $this->periodValidationRule(),
            'date_from' => 'required_if:period,custom|date_format:Y-m-d',
            'date_to' => 'required_if:period,custom|date_format:Y-m-d|after_or_equal:date_from',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'product_type' => 'nullable|in:mobile,accessory,spare_part',
            'product_id' => 'nullable|exists:products,id',
            'movement_date_from' => 'nullable|date_format:Y-m-d',
            'movement_date_to' => 'nullable|date_format:Y-m-d|after_or_equal:movement_date_from',
            'movement_type' => 'nullable|string|max:50',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('period') !== 'custom') {
                return;
            }

            if (!$this->input('date_from')) {
                $validator->errors()->add('date_from', 'Start date is required when period is custom');
            }

            if (!$this->input('date_to')) {
                $validator->errors()->add('date_to', 'End date is required when period is custom');
            }
        });
    }

    public function resolvedPeriod(): array
    {
        return [
            'period' => $this->input('period', 'this_month'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'movement_date_from' => $this->input('movement_date_from'),
            'movement_date_to' => $this->input('movement_date_to'),
            'snapshot_date' => now()->toDateString(),
        ];
    }
}
