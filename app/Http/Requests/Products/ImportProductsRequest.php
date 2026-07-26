<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\BaseApiRequest;

class ImportProductsRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ];
    }
}
