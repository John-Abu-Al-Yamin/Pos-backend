<?php

namespace App\Http\Requests\MarkupSetting;

use App\Http\Requests\BaseApiRequest;

class StoreMarkupSettingRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => 'required|string|in:new_mobile,used_mobile,accessory,spare_part|unique:markup_settings,product_type',
            'profit_percentage' => 'required|numeric|min:0|max:999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'product_type.required' => 'نوع المنتج مطلوب.',
            'product_type.in' => 'نوع المنتج يجب أن يكون new_mobile أو used_mobile أو accessory أو spare_part.',
            'product_type.unique' => 'يوجد already إعداد ربح لهذا النوع من المنتجات.',
            'profit_percentage.required' => 'نسبة الربح مطلوبة.',
            'profit_percentage.numeric' => 'نسبة الربح يجب أن تكون رقمًا.',
            'profit_percentage.min' => 'نسبة الربح لا يمكن أن تكون أقل من 0.',
            'profit_percentage.max' => 'نسبة الربح لا يمكن أن تتجاوز 999.99.',
        ];
    }
}
