<?php

namespace App\Http\Requests\PurchaseItem;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class StorePurchaseItemRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_header_id' => 'required|exists:purchase_headers,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'condition' => 'nullable|in:new,excellent,good,fair',
        ];
    }
}
