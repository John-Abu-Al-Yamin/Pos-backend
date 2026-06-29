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
        $rules = [
            'product_id' => 'required|integer|exists:products,id',
            'quantity_after' => 'required|integer|min:0',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ];

        $quantityBefore = $this->getQuantityBefore($this->input('product_id'));
        $difference = ($this->input('quantity_after') ?? 0) - $quantityBefore;

        if ($difference > 0) {
            $rules['unit_cost'] = 'required|numeric|min:0.01';
        } elseif ($difference !== 0) {
            $rules['unit_cost'] = 'nullable|numeric|min:0.01';
        }

        return $rules;
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
            'unit_cost.required' => 'تكلفة الوحدة مطلوبة عند زيادة المخزون',
            'unit_cost.numeric' => 'تكلفة الوحدة يجب أن تكون رقم',
            'unit_cost.min' => 'تكلفة الوحدة يجب أن تكون أكبر من 0',
        ];
    }

    private function getQuantityBefore(?int $productId): int
    {
        if (!$productId) {
            return 0;
        }

        return \App\Models\StockItem::where('product_id', $productId)
            ->where('status', 'available')
            ->count();
    }
}
