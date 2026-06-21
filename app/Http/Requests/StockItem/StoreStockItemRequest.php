<?php

namespace App\Http\Requests\StockItem;

use App\Http\Requests\BaseApiRequest;

class StoreStockItemRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'purchase_item_id' => 'nullable|exists:purchase_items,id',
            'serial_number' => 'nullable|string|unique:stock_items,serial_number',
            'cost_price' => 'required|numeric|min:0',
            'condition' => 'nullable|in:new,excellent,good,fair',
            'status' => 'nullable|in:available,sold,reserved,damaged,returned',
        ];
    }
}
