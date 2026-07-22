<?php

namespace App\Http\Requests\MaintenanceHeader;

use App\Http\Requests\BaseApiRequest;

class UpdateMaintenanceStatusRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,under_repair,waiting_parts,repaired,delivered,cancelled',
            'delivery_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة مطلوبة.',
            'status.in' => 'الحالة يجب أن تكون واحدة من: قيد الانتظار، قيد الإصلاح، انتظار قطع الغيار، تم الإصلاح، تم التسليم، ملغي.',
            'delivery_date.date' => 'تاريخ التسليم يجب أن يكون تاريخًا صحيحًا.',
        ];
    }
}
