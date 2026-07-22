<?php

namespace App\Http\Requests\MaintenanceOperation;

use App\Http\Requests\BaseApiRequest;

class UpdateMaintenanceOperationRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => 'sometimes|string',
            'operation_date' => 'sometimes|date',
            'technician' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'description.string' => 'وصف العملية يجب أن يكون نصًا.',
            'operation_date.date' => 'تاريخ العملية يجب أن يكون تاريخًا صحيحًا.',
            'technician.string' => 'اسم الفني يجب أن يكون نصًا.',
            'technician.max' => 'اسم الفني يجب ألا يزيد عن 255 حرفًا.',
            'cost.numeric' => 'التكلفة يجب أن تكون رقمًا.',
            'cost.min' => 'التكلفة لا يمكن أن تكون أقل من صفر.',
            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',
        ];
    }
}
