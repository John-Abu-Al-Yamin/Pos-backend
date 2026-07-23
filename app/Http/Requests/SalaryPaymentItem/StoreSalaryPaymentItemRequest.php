<?php

namespace App\Http\Requests\SalaryPaymentItem;

use App\Enums\SalaryPaymentItemType;
use App\Http\Requests\BaseApiRequest;

class StoreSalaryPaymentItemRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:' . implode(',', SalaryPaymentItemType::values())],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'نوع البند مطلوب.',
            'type.in' => 'نوع البند غير صالح.',
            'label.required' => 'التسمية مطلوبة.',
            'label.max' => 'التسمية يجب ألا تتجاوز 255 حرفاً.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً.',
            'amount.min' => 'المبلغ يجب أن يكون 0 على الأقل.',
        ];
    }
}
