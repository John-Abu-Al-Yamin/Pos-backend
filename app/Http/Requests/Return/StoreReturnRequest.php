<?php

namespace App\Http\Requests\Return;

use App\Http\Requests\BaseApiRequest;
use App\Models\SaleItem;
use App\Models\StockItem;

class StoreReturnRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_id' => 'required|integer|exists:sales,id',
            'refund_method' => 'required|string|in:cash,card,bank_transfer',
            'restocking_fee' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer|exists:sale_items,id',
            'items.*.stock_item_id' => 'nullable|integer|exists:stock_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.refund_amount' => 'required|numeric|min:0',
            'items.*.condition_after_inspection' => 'nullable|string|in:new,excellent,good,fair,damaged',
            'items.*.restock' => 'nullable|boolean',
            'items.*.reason' => 'nullable|string|max:500',
            'items.*.notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            $saleItems = SaleItem::whereIn('id', collect($data['items'])->pluck('sale_item_id'))
                ->with(['stockItems' => function ($q) {
                    $q->where('status', 'sold');
                }, 'returnItems'])
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $index => $item) {
                $saleItem = $saleItems->get($item['sale_item_id']);

                if (!$saleItem) {
                    $validator->errors()->add("items.{$index}.sale_item_id", 'الصنف غير موجود في الفاتورة');
                    continue;
                }

                if ($saleItem->sale_id != $data['sale_id']) {
                    $validator->errors()->add("items.{$index}.sale_item_id", 'الصنف لا ينتمي لهذه الفاتورة');
                    continue;
                }

                $totalSold = $saleItem->stockItems->count();
                $alreadyReturned = $saleItem->returnItems->sum('quantity');
                $returnable = $totalSold - $alreadyReturned;

                if ($item['quantity'] > $returnable) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "الكمية المرتجعة ({$item['quantity']}) تتجاوز الكمية القابلة للإرجاع ({$returnable})"
                    );
                }

                if ($item['stock_item_id']) {
                    $belongsToSale = $saleItem->stockItems->contains('id', $item['stock_item_id']);
                    if (!$belongsToSale) {
                        $validator->errors()->add(
                            "items.{$index}.stock_item_id",
                            'الجهاز لا ينتمي لهذا الصنف في الفاتورة'
                        );
                    }

                    $stockItem = StockItem::find($item['stock_item_id']);
                    if ($stockItem && $stockItem->status !== 'sold') {
                        $validator->errors()->add(
                            "items.{$index}.stock_item_id",
                            "الجهاز حالته {$stockItem->status} وليس مباعًا"
                        );
                    }
                }

                if ($item['refund_amount'] > 0 && $saleItem) {
                    $maxRefund = $saleItem->unit_price * $item['quantity'];
                    if ($item['refund_amount'] > $maxRefund) {
                        $validator->errors()->add(
                            "items.{$index}.refund_amount",
                            "المبلغ المسترد ({$item['refund_amount']}) يتجاوز الحد الأقصى ({$maxRefund})"
                        );
                    }
                }
                $restockingFee = (float) ($data['restocking_fee'] ?? 0);
                $totalItemRefund = collect($data['items'])->sum(fn ($i) => (float) ($i['refund_amount'] ?? 0));
                if ($restockingFee > $totalItemRefund) {
                    $validator->errors()->add(
                        'restocking_fee',
                        'رسوم إعادة التخزين لا يمكن أن تتجاوز إجمالي المبلغ المسترد'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'sale_id.required' => 'الفاتورة مطلوبة',
            'sale_id.exists' => 'الفاتورة غير موجودة',
            'refund_method.required' => 'طريقة الاسترداد مطلوبة',
            'refund_method.in' => 'طريقة الاسترداد يجب أن تكون نقدي أو بطاقة أو تحويل بنكي',
            'items.required' => 'يجب إرجاع صنف واحد على الأقل',
            'items.min' => 'يجب إرجاع صنف واحد على الأقل',
            'items.*.sale_item_id.required' => 'معرف الصنف مطلوب',
            'items.*.sale_item_id.exists' => 'الصنف غير موجود',
            'items.*.quantity.required' => 'الكمية مطلوبة',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.refund_amount.required' => 'المبلغ المسترد مطلوب',
            'items.*.refund_amount.min' => 'المبلغ المسترد يجب أن يكون 0 أو أكثر',
        ];
    }
}
