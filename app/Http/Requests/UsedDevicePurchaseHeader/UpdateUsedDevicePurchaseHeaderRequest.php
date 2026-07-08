<?php

namespace App\Http\Requests\UsedDevicePurchaseHeader;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUsedDevicePurchaseHeaderRequest extends BaseApiRequest
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
            'purchase_number' => 'sometimes|string|max:255|unique:used_device_purchase_headers,purchase_number,' . $this->route('id'),
            'customer_id'     => 'sometimes|exists:customers,id',
            'status'          => 'sometimes|in:draft,completed,cancelled',
            'total_amount'    => 'sometimes|numeric|min:0',
            'created_by'      => 'sometimes|exists:users,id',
            'notes'           => 'nullable|string',
            'completed_at'    => 'nullable|date',
            'cancelled_at'    => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_number.string'   => 'رقم الشراء يجب أن يكون نص.',
            'purchase_number.max'      => 'رقم الشراء يجب ألا يزيد عن 255 حرف.',
            'purchase_number.unique'   => 'رقم الشراء موجود بالفعل.',
            'customer_id.exists'       => 'العميل المحدد غير موجود.',
            'status.in'                => 'الحالة يجب أن تكون draft أو completed أو cancelled.',
            'total_amount.numeric'     => 'المبلغ الإجمالي يجب أن يكون رقم.',
            'total_amount.min'         => 'المبلغ الإجمالي يجب ألا يقل عن 0.',
            'created_by.exists'        => 'المستخدم المحدد غير موجود.',
            'notes.string'             => 'الملاحظات يجب أن تكون نص.',
            'completed_at.date'        => 'تاريخ الإكمال يجب أن يكون تاريخ صحيح.',
            'cancelled_at.date'        => 'تاريخ الإلغاء يجب أن يكون تاريخ صحيح.',
        ];
    }
}
