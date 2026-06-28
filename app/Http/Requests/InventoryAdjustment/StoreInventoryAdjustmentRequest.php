<?php

namespace App\Http\Requests\InventoryAdjustment;

use App\Http\Requests\BaseApiRequest;

class StoreInventoryAdjustmentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'quantity_after' => 'required|integer|min:0',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'المنتج مطلوب',
            'product_id.exists' => 'المنتج غير موجود',
            'quantity_after.required' => 'الكمية الجديدة مطلوبة',
            'quantity_after.integer' => 'الكمية الجديدة يجب أن تكون رقم صحيح',
            'quantity_after.min' => 'الكمية الجديدة يجب أن تكون 0 أو أكثر',
            'reason.required' => 'سبب التسوية مطلوب',
        ];
    }
}
