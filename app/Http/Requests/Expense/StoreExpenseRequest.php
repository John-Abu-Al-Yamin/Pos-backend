<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\BaseApiRequest;

class StoreExpenseRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:Rent,Salaries,Electricity,Water,Internet,Maintenance,Transportation,Office Supplies,Cleaning,Marketing,Taxes,Miscellaneous',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان المصروف مطلوب',
            'category.required' => 'التصنيف مطلوب',
            'category.in' => 'التصنيف غير صالح',
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric' => 'المبلغ يجب أن يكون رقم',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'expense_date.required' => 'تاريخ المصروف مطلوب',
            'expense_date.date' => 'تاريخ غير صالح',
        ];
    }
}
