<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends BaseApiRequest
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
            'name' => 'sometimes|required|string',
            'category_id' => 'sometimes|required',
            'is_serialized' => 'nullable|boolean',
            'product_category' => 'sometimes|required|string|in:mobile,part,accessory',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنتج مطلوب',
            'name.string' => 'اسم المنتج يجب أن يكون نصًا',
            'category_id.required' => 'معرف الفئة مطلوب',
            'is_serialized.boolean' => 'يجب اختيار نوع المنتج (موبايل أو إكسسوار)',
            'product_category.required' => 'تصنيف المنتج مطلوب',
            'product_category.in' => 'تصنيف المنتج يجب أن يكون: موبايل، قطعة غيار، أو اكسسوار',
        ];
    }
}
