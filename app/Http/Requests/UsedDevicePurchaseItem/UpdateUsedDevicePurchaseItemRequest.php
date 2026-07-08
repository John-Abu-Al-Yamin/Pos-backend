<?php

namespace App\Http\Requests\UsedDevicePurchaseItem;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateUsedDevicePurchaseItemRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'sometimes',
                'integer',
                Rule::exists('products', 'id'),
            ],
            'quantity' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],
            'unit_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'screen_condition' => [
                'nullable',
                'string',
                'max:255',
            ],
            'body_condition' => [
                'nullable',
                'string',
                'max:255',
            ],
            'fingerprint_working' => [
                'nullable',
                'boolean',
            ],
            'face_id_working' => [
                'nullable',
                'boolean',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.integer'  => 'المنتج غير صالح.',
            'product_id.exists'   => 'المنتج غير موجود.',
            'quantity.numeric'    => 'الكمية يجب أن تكون رقمًا.',
            'quantity.gt'         => 'الكمية يجب أن تكون أكبر من صفر.',
            'unit_price.numeric'  => 'سعر الوحدة يجب أن يكون رقمًا.',
            'unit_price.min'      => 'سعر الوحدة لا يمكن أن يكون أقل من صفر.',
            'screen_condition.string'  => 'حالة الشاشة يجب أن تكون نصًا.',
            'screen_condition.max'     => 'حالة الشاشة يجب ألا تزيد عن 255 حرفًا.',
            'body_condition.string'    => 'حالة الهيكل يجب أن تكون نصًا.',
            'body_condition.max'       => 'حالة الهيكل يجب ألا تزيد عن 255 حرفًا.',
            'fingerprint_working.boolean' => 'حالة البصمة يجب أن تكون قيمة منطقية.',
            'face_id_working.boolean'     => 'حالة Face ID يجب أن تكون قيمة منطقية.',
            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',
        ];
    }
}
