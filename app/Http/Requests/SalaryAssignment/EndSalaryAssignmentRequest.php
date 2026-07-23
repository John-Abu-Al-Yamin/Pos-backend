<?php

namespace App\Http\Requests\SalaryAssignment;

use App\Http\Requests\BaseApiRequest;

class EndSalaryAssignmentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.required' => 'تاريخ الانتهاء مطلوب.',
            'end_date.date' => 'تاريخ الانتهاء غير صالح.',
            'reason.max' => 'السبب يجب ألا يتجاوز 500 حرف.',
        ];
    }
}
