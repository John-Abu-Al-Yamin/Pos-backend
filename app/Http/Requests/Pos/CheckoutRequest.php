<?php

namespace App\Http\Requests\Pos;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends BaseApiRequest
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
            'customer_id' => ['nullable', 'exists:customers,id'],

            'discount_amount' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.product_id' => [
                'nullable',
                'required_without:items.*.inventory_item_id',
                'exists:products,id',
            ],

            'items.*.inventory_item_id' => [
                'nullable',
                'required_without:items.*.product_id',
                'exists:inventory_items,id',
            ],

            'items.*.quantity' => [
                'required_without:items.*.inventory_item_id',
                'integer',
                'min:1',
            ],

            'items.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }
    public function messages(): array
    {
        return [

            'customer_id.exists' => 'العميل اللي اخترته مش موجود.',

            'discount_amount.numeric' => 'الخصم لازم يكون رقم.',
            'discount_amount.min' => 'الخصم مينفعش يكون أقل من صفر.',

            'notes.string' => 'الملاحظات لازم تكون نص.',

            'items.required' => 'لازم تضيف منتج واحد على الأقل.',
            'items.array' => 'بيانات المنتجات غير صحيحة.',
            'items.min' => 'لازم تضيف منتج واحد على الأقل.',

            'items.*.product_id.required_without' => 'لازم تختار المنتج.',
            'items.*.product_id.exists' => 'المنتج المختار مش موجود.',

            'items.*.inventory_item_id.required_without' => 'لازم تختار الجهاز.',
            'items.*.inventory_item_id.exists' => 'الجهاز المختار مش موجود.',

            'items.*.quantity.required_without' => 'الكمية مطلوبة للمنتجات.',
            'items.*.quantity.integer' => 'الكمية لازم تكون رقم صحيح.',
            'items.*.quantity.min' => 'الكمية لازم تكون 1 على الأقل.',

            'items.*.unit_price.required' => 'سعر البيع مطلوب.',
            'items.*.unit_price.numeric' => 'سعر البيع لازم يكون رقم.',
            'items.*.unit_price.min' => 'سعر البيع مينفعش يكون أقل من صفر.',
        ];
    }
}
