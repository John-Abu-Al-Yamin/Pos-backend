<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'phone' => [
                'sometimes',
                'string',
                Rule::unique('customers', 'phone')->ignore($this->route('id')),
            ],
        ];
    }
}
