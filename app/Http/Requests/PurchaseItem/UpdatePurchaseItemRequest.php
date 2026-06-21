<?php

namespace App\Http\Requests\PurchaseItem;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdatePurchaseItemRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_header_id' => 'sometimes|exists:purchase_headers,id',
            'product_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'unit_cost' => 'sometimes|numeric|min:0',
        ];
    }
}
