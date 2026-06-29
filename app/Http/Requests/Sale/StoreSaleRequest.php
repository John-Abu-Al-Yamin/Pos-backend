<?php

namespace App\Http\Requests\Sale;

use App\Http\Requests\BaseApiRequest;
use App\Models\Product;

class StoreSaleRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'sometimes|in:cash,card,transfer,installment',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'sometimes|integer|min:1|max:99999',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.stock_item_ids' => 'sometimes|array',
            'items.*.stock_item_ids.*' => 'exists:stock_items,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $product = Product::find($item['product_id'] ?? null);
                if (!$product) {
                    continue;
                }

                $unitPrice = (float) ($item['unit_price'] ?? 0);

                if ($product->selling_price > 0 && $unitPrice > $product->selling_price) {
                    $validator->errors()->add(
                        "items.{$index}.unit_price",
                        "سعر البيع ({$unitPrice}) يتجاوز السعر المحدد للمنتج ({$product->selling_price})."
                    );
                }

                if ($unitPrice <= 0) {
                    $validator->errors()->add(
                        "items.{$index}.unit_price",
                        'سعر البيع يجب أن يكون أكبر من صفر.',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة صنف واحد على الأقل',
            'items.*.product_id.required' => 'المنتج مطلوب',
            'items.*.unit_price.required' => 'سعر البيع مطلوب',
            'items.*.unit_price.min' => 'سعر البيع لا يمكن أن يكون سالبًا',
            'items.*.quantity.max' => 'الكمية كبيرة جدًا',
        ];
    }
}
