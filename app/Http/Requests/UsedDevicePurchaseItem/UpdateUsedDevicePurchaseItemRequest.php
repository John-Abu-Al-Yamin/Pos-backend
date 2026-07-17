<?php

namespace App\Http\Requests\UsedDevicePurchaseItem;

use App\Http\Requests\BaseApiRequest;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                'integer',
                'min:1',
            ],
            'unit_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'battery_health' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
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

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $productId = $this->input('product_id');
            if (!$productId) {
                return;
            }

            $product = Product::find($productId);
            if (!$product) {
                return;
            }

            if ($product->type !== 'mobile') {
                $validator->errors()->add(
                    'product_id',
                    'فقط الأجهزة المحمولة مسموح بها في شراء الأجهزة المستعملة.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'product_id.integer'  => 'المنتج غير صالح.',
            'product_id.exists'   => 'المنتج غير موجود.',
            'quantity.integer'    => 'كمية الأجهزة المحمولة يجب أن تكون رقمًا صحيحًا.',
            'quantity.min'        => 'الكمية يجب أن تكون 1 على الأقل.',
            'unit_price.numeric'  => 'سعر الوحدة يجب أن يكون رقمًا.',
            'unit_price.min'      => 'سعر الوحدة لا يمكن أن يكون أقل من صفر.',
            'battery_health.integer' => 'نسبة البطارية يجب أن تكون رقمًا صحيحًا.',
            'battery_health.min'     => 'نسبة البطارية يجب ألا تقل عن 0.',
            'battery_health.max'     => 'نسبة البطارية يجب ألا تزيد عن 100.',
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
