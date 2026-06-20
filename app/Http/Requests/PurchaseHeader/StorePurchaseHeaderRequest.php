<?php

namespace App\Http\Requests\PurchaseHeader;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseHeaderRequest extends BaseApiRequest
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
            //
            'supplier_id' => 'nullable|exists:suppliers,id',
            'date' => 'required|date',
            // 'total' => 'required|numeric',
            'type' => 'required',
        ];
    }

    public function messages(): array
    {
        return [

            'date.required' => 'تاريخ الشراء مطلوب',
            'date.date' => 'تاريخ الشراء يجب ان يكون تاريخًا',
            // 'total.required' => 'المبلغ الكلي مطلوب',
            // 'total.numeric' => 'المبلغ الكلي يجب ان يكون رقمًا',
            'type.required' => 'نوع الشراء مطلوب',
        ];
    }
}
