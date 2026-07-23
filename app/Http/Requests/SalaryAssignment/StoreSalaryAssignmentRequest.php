<?php

namespace App\Http\Requests\SalaryAssignment;

use App\Http\Requests\BaseApiRequest;
use App\Models\SalaryAssignment;

class StoreSalaryAssignmentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $exists = SalaryAssignment::where('user_id', $this->user_id)->exists();

            if ($exists) {
                $validator->errors()->add('user_id', 'يوجد بالفعل تخصيص راتب لهذا الموظف.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'يجب اختيار مستخدم.',
            'user_id.exists' => 'المستخدم غير موجود.',
            'base_salary.required' => 'الراتب الأساسي مطلوب.',
            'base_salary.numeric' => 'الراتب الأساسي يجب أن يكون رقماً.',
            'base_salary.min' => 'الراتب الأساسي يجب أن يكون 0 على الأقل.',
        ];
    }
}
