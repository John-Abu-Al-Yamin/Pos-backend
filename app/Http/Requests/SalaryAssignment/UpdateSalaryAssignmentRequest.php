<?php

namespace App\Http\Requests\SalaryAssignment;

use App\Http\Requests\BaseApiRequest;

class UpdateSalaryAssignmentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'base_salary.numeric' => 'الراتب الأساسي يجب أن يكون رقماً.',
            'base_salary.min' => 'الراتب الأساسي يجب أن يكون 0 على الأقل.',
        ];
    }
}
