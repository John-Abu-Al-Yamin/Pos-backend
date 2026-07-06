<?php

namespace App\Http\Requests\PurchaseItem;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id'),
            ],

            'quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'unit_cost' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'المنتج مطلوب.',
            'product_id.integer'  => 'المنتج غير صالح.',
            'product_id.exists'   => 'المنتج غير موجود.',

            'quantity.required' => 'الكمية مطلوبة.',
            'quantity.numeric'  => 'الكمية يجب أن تكون رقمًا.',
            'quantity.gt'       => 'الكمية يجب أن تكون أكبر من صفر.',

            'unit_cost.required' => 'سعر الشراء مطلوب.',
            'unit_cost.numeric'  => 'سعر الشراء يجب أن يكون رقمًا.',
            'unit_cost.min'      => 'سعر الشراء لا يمكن أن يكون أقل من صفر.',
        ];
    }
}
