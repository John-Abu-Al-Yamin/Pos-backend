<?php

namespace App\Http\Requests\Brand;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends BaseApiRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('brands', 'name')->ignore($this->brand),
            ],
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'الاسم يجب أن يكون نصًا.',
            'name.unique' => 'هذا الاسم موجود بالفعل.',
            'is_active.boolean' => 'حالة النشاط يجب أن تكون قيمة منطقية.',
        ];
    }
}
