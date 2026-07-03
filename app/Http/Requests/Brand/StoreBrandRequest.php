<?php

namespace App\Http\Requests\Brand;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreBrandRequest extends BaseApiRequest
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
            'name' => 'required|string|max:255|unique:brands,name',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'name.string' => 'الاسم يجب أن يكون نصًا.',
            'name.unique' => 'الاسم يجب أن يكون فريدًا.',
            'is_active.boolean' => 'حالة النشاط يجب أن تكون قيمة منطقية.',
        ];
    }
}
