<?php

namespace App\Http\Requests\PurchaseReturn;

use App\Http\Requests\BaseApiRequest;

class StorePurchaseReturnRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_header_id' => ['required', 'exists:purchase_headers,id'],

            'supplier_id' => ['nullable', 'exists:suppliers,id'],

            'reason' => ['nullable', 'string'],

            'refund_method' => ['nullable', 'string'],

            'return_date' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.purchase_item_id' => [
                'required',
                'exists:purchase_items,id',
            ],

            'items.*.inventory_item_id' => [
                'nullable',
                'exists:inventory_items,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.unit_refund_amount' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }
}
