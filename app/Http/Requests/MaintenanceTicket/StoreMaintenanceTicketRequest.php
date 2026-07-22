<?php

namespace App\Http\Requests\MaintenanceTicket;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Validator;

class StoreMaintenanceTicketRequest extends BaseApiRequest
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
            'product_id' => 'nullable|exists:products,id',
            'serial_number' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'condition_notes' => 'nullable|string',
            'problem_description' => 'required|string',
            'received_date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:received_date',
            'advance_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $advancePayment = $this->input('advance_payment');

            // Initial total cost is 0 since no parts/operations are added yet. 
            // In a real scenario, you might want advance payment to just exist as credit, 
            // but for ticket creation, we don't strictly bind it to a pre-defined total cost anymore.
        });
    }

    public function messages(): array
    {
        return [
            'maintenance_device_id.exists' => 'الجهاز المحدد غير موجود.',
            'customer_id.exists' => 'العميل المحدد غير موجود.',
            'product_id.exists' => 'المنتج المحدد غير موجود.',
            'serial_number.string' => 'الرقم التسلسلي يجب أن يكون نصًا.',
            'serial_number.max' => 'الرقم التسلسلي يجب ألا يزيد عن 255 حرفًا.',
            'color.string' => 'اللون يجب أن يكون نصًا.',
            'color.max' => 'اللون يجب ألا يزيد عن 255 حرفًا.',
            'condition_notes.string' => 'ملاحظات الحالة يجب أن تكون نصًا.',
            'problem_description.required' => 'وصف المشكلة مطلوب.',
            'problem_description.string' => 'وصف المشكلة يجب أن يكون نصًا.',
            'received_date.required' => 'تاريخ الاستلام مطلوب.',
            'received_date.date' => 'تاريخ الاستلام يجب أن يكون تاريخًا صحيحًا.',
            'delivery_date.date' => 'تاريخ التسليم يجب أن يكون تاريخًا صحيحًا.',
            'delivery_date.after_or_equal' => 'تاريخ التسليم يجب أن يكون بعد أو يساوي تاريخ الاستلام.',
            'advance_payment.numeric' => 'الدفعة المقدمة يجب أن تكون رقمًا.',
            'advance_payment.min' => 'الدفعة المقدمة لا يمكن أن تكون أقل من صفر.',
            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',
        ];
    }
}
