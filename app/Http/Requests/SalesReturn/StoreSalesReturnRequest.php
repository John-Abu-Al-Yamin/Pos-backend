<?php

namespace App\Http\Requests\SalesReturn;

use App\Http\Requests\BaseApiRequest;

class StoreSalesReturnRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_header_id' => ['required', 'exists:sales_headers,id'],

            'customer_id' => ['nullable', 'exists:customers,id'],

            'reason' => ['nullable', 'string'],

            'refund_method' => ['nullable', 'string'],

            'return_date' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.sales_item_id' => [
                'required',
                'exists:sales_items,id',
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
