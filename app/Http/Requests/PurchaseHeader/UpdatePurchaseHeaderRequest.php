<?php

namespace App\Http\Requests\PurchaseHeader;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseHeaderRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => 'sometimes|exists:suppliers,id',

            'status' => 'sometimes|in:draft,completed,cancelled',

            'notes' => 'nullable|string',


            'supplier_invoice_number' => 'nullable|string|max:255',
        ];
    }
    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'المورد المختار غير موجود.',

            'status.in' => 'حالة الفاتورة يجب أن تكون: draft أو completed أو cancelled.',

            'notes.string' => 'الملاحظات يجب أن تكون نص.',


            'supplier_invoice_number.string' => 'رقم فاتورة المورد يجب أن يكون نص.',
            'supplier_invoice_number.max' => 'رقم فاتورة المورد يجب ألا يزيد عن 255 حرف.',
        ];
    }
}
