<?php

namespace App\Http\Requests\Suppliers;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSuppliersRequest extends BaseApiRequest
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
            'name' => 'required|string|max:255|unique:suppliers,name,' . $this->route('id'),
            'phone' => 'required|string|max:20|unique:suppliers,phone,' . $this->route('id'),



        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المورد مطلوب.',
            'name.string' => 'اسم المورد يجب أن يكون نصًا.',
            'name.max' => 'اسم المورد لا يجب أن يتجاوز 255 حرفًا.',
            'name.unique' => 'اسم المورد موجود بالفعل.',

            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصًا.',
            'phone.max' => 'رقم الهاتف لا يجب أن يتجاوز 20 حرفًا.',
            'phone.unique' => 'رقم الهاتف موجود بالفعل.',
        ];
    }
}
