<?php

namespace App\Http\Requests\SalaryPayment;

use App\Http\Requests\BaseApiRequest;

class StoreSalaryPaymentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'يجب اختيار مستخدم.',
            'user_id.exists' => 'المستخدم غير موجود.',
        ];
    }
}
