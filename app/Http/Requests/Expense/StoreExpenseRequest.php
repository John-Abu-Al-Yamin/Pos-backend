<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category' => [
                'required',
                'string',
                Rule::in([
                    'electricity',
                    'water',
                    'internet',
                    'rent',
                    'salary',
                    'cleaning',
                    'maintenance',
                    'phone_bills',
                    'office_supplies',
                    'equipment',
                    'packaging',
                    'security_cameras',
                    'taxes',
                    'other',
                ]),
            ],
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category.required' => 'التصنيف مطلوب.',
            'expense_category.in' => 'التصنيف المحدد غير صالح.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقمًا.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'expense_date.required' => 'تاريخ المصروف مطلوب.',
            'expense_date.date' => 'تاريخ المصروف غير صالح.',
            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',
        ];
    }
}
