<?php

namespace App\Http\Requests\SalesReturnHeader;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreSalesReturnHeaderRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_header_id' => 'required|exists:sales_headers,id',
            'reason' => 'nullable|string',
            'return_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.sales_item_id' => 'required|exists:sales_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.inventory_item_id' => 'nullable|exists:inventory_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_refund_amount' => 'required|numeric|min:0',
            'items.*.total_refund' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'sales_header_id.required' => 'يجب اختيار فاتورة البيع.',
            'sales_header_id.exists' => 'فاتورة البيع المختارة غير موجودة.',
            'reason.string' => 'سبب الإرجاع يجب أن يكون نص.',
            'return_date.date' => 'تاريخ الإرجاع يجب أن يكون تاريخ صحيح.',
            'items.required' => 'يجب اختيار صنف واحد على الأقل للإرجاع.',
            'items.min' => 'يجب اختيار صنف واحد على الأقل للإرجاع.',
            'items.*.sales_item_id.required' => 'بيانات الصنف غير صحيحة.',
            'items.*.sales_item_id.exists' => 'الصنف المختار غير موجود.',
            'items.*.product_id.required' => 'بيانات المنتج غير صحيحة.',
            'items.*.product_id.exists' => 'المنتج المختار غير موجود.',
            'items.*.inventory_item_id.exists' => 'الجهاز المختار غير موجود.',
            'items.*.quantity.required' => 'الكمية مطلوبة.',
            'items.*.quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'items.*.unit_refund_amount.required' => 'مبلغ الوحدة مطلوب.',
            'items.*.unit_refund_amount.min' => 'مبلغ الوحدة يجب أن يكون أكبر من صفر.',
            'items.*.total_refund.required' => 'الإجمالي مطلوب.',
            'items.*.total_refund.min' => 'الإجمالي يجب أن يكون أكبر من صفر.',
        ];
    }
}
