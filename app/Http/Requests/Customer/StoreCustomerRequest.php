<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseApiRequest;

class StoreCustomerRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:customers,phone',
          
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم العميل مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم من قبل',
        ];
    }
}
