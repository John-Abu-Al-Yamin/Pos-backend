<?php

namespace App\Http\Requests\Sale;

use App\Http\Requests\BaseApiRequest;

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
            'date' => 'sometimes|date',
            'payment_method' => 'sometimes|in:cash,card,transfer,installment',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'sometimes|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.stock_item_ids' => 'sometimes|array',
            'items.*.stock_item_ids.*' => 'exists:stock_items,id',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة صنف واحد على الأقل',
            'items.*.product_id.required' => 'المنتج مطلوب',
            'items.*.unit_price.required' => 'سعر البيع مطلوب',
            'items.*.unit_price.min' => 'سعر البيع لا يمكن أن يكون سالبًا',
        ];
    }
}
