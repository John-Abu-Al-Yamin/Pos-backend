<?php

namespace App\Http\Requests\PurchaseHeader;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

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
            'supplier_id' => [
                'sometimes',
                'nullable',
                'exists:suppliers,id',
                'required_if:type,purchase',
            ],
            'date' => 'sometimes|required|date',
            'type' => 'sometimes|required|in:purchase,opening_stock',
            'reference' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'معرف المورد مطلوب',
            'date.required' => 'تاريخ الشراء مطلوب',
            'date.date' => 'تاريخ الشراء يجب ان يكون تاريخًا',
            'type.required' => 'نوع الشراء مطلوب',
            'type.in' => 'نوع الشراء يجب ان يكون purchase أو opening_stock',
            'reference.string' => 'المرجع يجب ان يكون نصًا',
            'reference.max' => 'المرجع يجب ان لا يتجاوز 255 حرفًا',
        ];
    }
}
