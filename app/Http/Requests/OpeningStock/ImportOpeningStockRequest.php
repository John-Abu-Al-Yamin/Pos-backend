<?php

namespace App\Http\Requests\OpeningStock;

use App\Exports\OpeningStockTemplateExport;
use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class ImportOpeningStockRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'template_type' => ['required', 'string', Rule::in([
                OpeningStockTemplateExport::TYPE_MOBILE,
                OpeningStockTemplateExport::TYPE_QUANTITY,
            ])],
        ];
    }
}
