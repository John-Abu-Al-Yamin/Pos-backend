<?php

namespace App\Http\Requests\MaintenanceHeader;

use App\Http\Requests\BaseApiRequest;

class UpdateMaintenanceHeaderRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_device_id' => 'nullable|exists:maintenance_devices,id',
            'customer_id' => 'nullable|exists:customers,id',
            'problem_description' => 'sometimes|string',
            'received_date' => 'sometimes|date',
            'delivery_date' => 'nullable|date',
            'advance_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'maintenance_device_id.exists' => 'الجهاز المحدد غير موجود.',
            'customer_id.exists' => 'العميل المحدد غير موجود.',
            'problem_description.string' => 'وصف المشكلة يجب أن يكون نصًا.',
            'received_date.date' => 'تاريخ الاستلام يجب أن يكون تاريخًا صحيحًا.',
            'delivery_date.date' => 'تاريخ التسليم يجب أن يكون تاريخًا صحيحًا.',
            'advance_payment.numeric' => 'الدفعة المقدمة يجب أن تكون رقمًا.',
            'advance_payment.min' => 'الدفعة المقدمة لا يمكن أن تكون أقل من صفر.',
            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',
        ];
    }
}
