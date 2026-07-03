<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\BaseApiRequest;

class UpdateProductRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            //
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id'    => 'nullable|exists:brands,id',
            'name'        => 'sometimes|string|max:255',
            'type'        => 'sometimes|in:mobile,accessory,spare_part',
            'min_stock'   => 'nullable|integer|min:0',
        ];
    }
    public function messages(): array
    {
        return [
            'category_id.exists'   => 'الفئة المحددة غير موجودة.',
            'brand_id.exists'      => 'الماركة المحددة غير موجودة.',
            'name.string'          => 'حقل الاسم يجب أن يكون نصًا.',
            'name.max'             => 'حقل الاسم يجب ألا يزيد عن 255 حرفًا.',
            'type.in'              => 'نوع المنتج يجب أن يكون mobile أو accessory أو spare_part.',
            'min_stock.integer'    => 'حقل الحد الأدنى للمخزون يجب أن يكون عددًا صحيحًا.',
            'min_stock.min'        => 'حقل الحد الأدنى للمخزون يجب أن يكون صفرًا على الأقل.',
        ];
    }
}
