<?php

namespace App\Http\Requests\Repair;

use App\Http\Requests\BaseApiRequest;

class VoidRepairRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'void_reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'void_reason.required' => 'سبب الإلغاء مطلوب',
        ];
    }
}
