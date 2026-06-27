<?php

namespace App\Http\Requests\Repair;

use App\Http\Requests\BaseApiRequest;

class UpdateRepairRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'device_type' => 'sometimes|string|max:255',
            'device_serial' => 'nullable|string|max:255',
            'issue_description' => 'sometimes|string',
            'work_description' => 'nullable|string',
            'estimated_cost' => 'nullable|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'expected_delivery_date' => 'nullable|date',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'parts' => 'nullable|array',
            'parts.*.stock_item_id' => 'required|exists:stock_items,id',
        ];
    }

    public function messages(): array
    {
        return [
            'device_type.sometimes' => 'نوع الجهاز مطلوب',
            'issue_description.sometimes' => 'وصف المشكلة مطلوب',
        ];
    }
}
