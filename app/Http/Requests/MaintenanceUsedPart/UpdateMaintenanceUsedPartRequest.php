<?php

namespace App\Http\Requests\MaintenanceUsedPart;

use App\Http\Requests\BaseApiRequest;

class UpdateMaintenanceUsedPartRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'الكمية مطلوبة.',
            'quantity.numeric' => 'الكمية يجب أن تكون رقمًا.',
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
        ];
    }
}
