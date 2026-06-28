<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\BaseApiRequest;

class UpdateExpenseRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|in:Rent,Salaries,Electricity,Water,Internet,Maintenance,Transportation,Office Supplies,Cleaning,Marketing,Taxes,Miscellaneous',
            'amount' => 'sometimes|numeric|min:0.01',
            'expense_date' => 'sometimes|date',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'title.string' => 'عنوان المصروف غير صالح',
            'category.in' => 'التصنيف غير صالح',
            'amount.numeric' => 'المبلغ يجب أن يكون رقم',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'expense_date.date' => 'تاريخ غير صالح',
        ];
    }
}
