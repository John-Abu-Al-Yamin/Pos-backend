<?php

namespace App\Http\Requests\MaintenanceUsedPart;

use App\Http\Requests\BaseApiRequest;
use App\Models\Product;
use Illuminate\Validation\Validator;

class StoreMaintenanceUsedPartRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
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

            if ($product->type !== 'spare_part') {
                $validator->errors()->add(
                    'product_id',
                    'فقط قطع الغيار مسموح بها في استخدام قطع الصيانة.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'المنتج مطلوب.',
            'product_id.exists' => 'المنتج المحدد غير موجود.',
            'quantity.required' => 'الكمية مطلوبة.',
            'quantity.numeric' => 'الكمية يجب أن تكون رقمًا.',
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
        ];
    }
}
