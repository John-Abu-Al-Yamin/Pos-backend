<?php

namespace App\Http\Requests\StockItem;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateStockItemRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'sometimes|exists:products,id',
            'purchase_item_id' => 'nullable|exists:purchase_items,id',
            'serial_number' => [
                'nullable',
                'string',
                Rule::unique('stock_items', 'serial_number')->ignore($this->route('id')),
            ],
            'cost_price' => 'sometimes|numeric|min:0',
            'condition' => 'nullable|in:new,excellent,good,fair',
            'status' => 'nullable|in:available,sold,reserved,damaged,returned',
        ];
    }
}
