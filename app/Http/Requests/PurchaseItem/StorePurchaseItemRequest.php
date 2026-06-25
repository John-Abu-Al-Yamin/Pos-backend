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
            'device_details' => 'nullable|array',
            'device_details.*.battery_health' => 'nullable|integer|min:0|max:100',
            'device_details.*.screen_condition' => 'nullable|in:perfect,good,scratched,cracked,broken',
            'device_details.*.body_condition' => 'nullable|in:perfect,good,scratched,dented,worn',
            'device_details.*.face_id_working' => 'nullable|boolean',
            'device_details.*.fingerprint_working' => 'nullable|boolean',
            'device_details.*.camera_working' => 'nullable|boolean',
            'device_details.*.speaker_working' => 'nullable|boolean',
            'device_details.*.accessories' => 'nullable|string|max:500',
            'device_details.*.notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();
            if (!empty($data['device_details']) && isset($data['quantity'])) {
                $quantity = (int) $data['quantity'];
                $detailCount = count($data['device_details']);
                if ($detailCount !== $quantity) {
                    $validator->errors()->add(
                        'device_details',
                        "عدد تفاصيل الأجهزة ({$detailCount}) يجب أن يساوي الكمية ({$quantity})."
                    );
                }
            }
        });
    }
}
