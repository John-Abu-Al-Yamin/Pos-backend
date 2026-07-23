<?php

namespace App\Http\Requests\SalaryPayment;

use App\Http\Requests\BaseApiRequest;

class UpdateSalaryPaymentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
