<?php

namespace App\Http\Requests\PurchaseItem;

use App\Http\Requests\BaseApiRequest;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseItemRequest extends BaseApiRequest
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
            'purchase_header_id' => [
                'required',
                'integer',
                Rule::exists('purchase_headers', 'id'),
            ],

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

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $productId = $this->input('product_id');
            $quantity = $this->input('quantity');

            if (!$productId || !$quantity) {
                return;
            }

            $product = Product::find($productId);
            if (!$product) {
                return;
            }

            if ($product->type === 'mobile' && (float) $quantity != (int) $quantity) {
                $validator->errors()->add(
                    'quantity',
                    'كمية الأجهزة المحمولة يجب أن تكون رقمًا صحيحًا.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'purchase_header_id.required' => 'فاتورة الشراء مطلوبة.',
            'purchase_header_id.integer'  => 'فاتورة الشراء غير صالحة.',
            'purchase_header_id.exists'   => 'فاتورة الشراء غير موجودة.',

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
