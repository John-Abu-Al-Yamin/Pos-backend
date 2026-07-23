<?php

namespace App\Http\Requests\SalaryPaymentItem;

use App\Enums\SalaryPaymentItemType;
use App\Http\Requests\BaseApiRequest;

class UpdateSalaryPaymentItemRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:' . implode(',', SalaryPaymentItemType::values())],
            'label' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'نوع البند غير صالح.',
            'label.max' => 'التسمية يجب ألا تتجاوز 255 حرفاً.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً.',
            'amount.min' => 'المبلغ يجب أن يكون 0 على الأقل.',
        ];
    }
}
