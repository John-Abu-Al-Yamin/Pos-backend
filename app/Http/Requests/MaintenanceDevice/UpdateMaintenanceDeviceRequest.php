<?php

namespace App\Http\Requests\MaintenanceDevice;

use App\Http\Requests\BaseApiRequest;

class UpdateMaintenanceDeviceRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'nullable|exists:products,id',
            'serial_number' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'condition_notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.exists' => 'المنتج المحدد غير موجود.',
            'serial_number.string' => 'الرقم التسلسلي يجب أن يكون نصًا.',
            'serial_number.max' => 'الرقم التسلسلي يجب ألا يزيد عن 255 حرفًا.',
            'color.string' => 'اللون يجب أن يكون نصًا.',
            'color.max' => 'اللون يجب ألا يزيد عن 255 حرفًا.',
            'condition_notes.string' => 'ملاحظات الحالة يجب أن تكون نصًا.',
        ];
    }
}
