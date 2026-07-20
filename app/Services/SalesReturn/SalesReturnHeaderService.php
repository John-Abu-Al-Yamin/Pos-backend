<?php

namespace App\Services\SalesReturn;

use App\Models\SalesReturnHeader;
use App\Models\SalesHeader;
use App\Models\SalesReturnItem;
use Illuminate\Support\Facades\DB;

class SalesReturnHeaderService
{
    public function createDraft(array $data): SalesReturnHeader
    {
        $salesHeader = SalesHeader::findOrFail($data['sales_header_id']);

        return DB::transaction(function () use ($salesHeader, $data) {
            $totalRefund = collect($data['items'])->sum('total_refund');

            $return = SalesReturnHeader::create([
                'sales_header_id' => $salesHeader->id,
                'return_number' => $this->generateReturnNumber(),
                'customer_id' => $salesHeader->customer_id,
                'user_id' => auth()->id(),
                'status' => 'draft',
                'total_refund_amount' => $totalRefund,
                'reason' => $data['reason'] ?? null,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
            ]);

            foreach ($data['items'] as $itemData) {
                SalesReturnItem::create([
                    'sales_return_header_id' => $return->id,
                    'sales_item_id' => $itemData['sales_item_id'],
                    'product_id' => $itemData['product_id'],
                    'inventory_item_id' => $itemData['inventory_item_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_refund_amount' => $itemData['unit_refund_amount'],
                    'total_refund' => $itemData['total_refund'],
                ]);
            }

            return $return->fresh()->load([
                'salesHeader',
                'customer',
                'user',
                'items.product',
                'items.inventoryItem',
            ]);
        });
    }

    private function generateReturnNumber(): string
    {
        $lastReturn = SalesReturnHeader::latest('id')->first();
        $nextNumber = $lastReturn ? $lastReturn->id + 1 : 1;
        return 'SR-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
