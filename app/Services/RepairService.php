<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Repair;
use App\Models\RepairPart;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class RepairService
{
    public function createRepair(array $data): Repair
    {
        return DB::transaction(function () use ($data) {
            $repair = Repair::create([
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'device_type' => $data['device_type'],
                'device_serial' => $data['device_serial'] ?? null,
                'issue_description' => $data['issue_description'],
                'work_description' => $data['work_description'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? 0,
                'deposit' => $data['deposit'] ?? 0,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
            ]);

            if (!empty($data['parts'])) {
                $this->attachParts($repair, $data['parts']);
            }

            $repair->load(['customer', 'repairParts.product', 'repairParts.stockItem']);

            return $repair;
        });
    }

    public function updateRepair(Repair $repair, array $data): Repair
    {
        return DB::transaction(function () use ($repair, $data) {
            $updatable = [
                'customer_id', 'customer_name', 'customer_phone',
                'device_type', 'device_serial', 'issue_description',
                'work_description', 'estimated_cost', 'deposit',
                'expected_delivery_date', 'status',
            ];

            $fillData = [];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $data)) {
                    $fillData[$field] = $data[$field];
                }
            }

            $repair->update($fillData);

            if (isset($data['parts'])) {
                $oldPartIds = $repair->repairParts()->pluck('stock_item_id');
                StockItem::whereIn('id', $oldPartIds)
                    ->where('status', 'consumed')
                    ->update(['status' => 'available']);
                $repair->repairParts()->delete();

                if (!empty($data['parts'])) {
                    $this->attachParts($repair, $data['parts']);
                }
            }

            $repair->load(['customer', 'repairParts.product', 'repairParts.stockItem']);

            return $repair;
        });
    }

    private function attachParts(Repair $repair, array $parts): void
    {
        $totalPartsCost = 0;

        foreach ($parts as $partData) {
            $stockItem = StockItem::lockForUpdate()->findOrFail($partData['stock_item_id']);

            if ($stockItem->status !== 'available') {
                throw new \RuntimeException(
                    "قطعة الغيار #{$stockItem->id} غير متاحة (الحالة: {$stockItem->status})."
                );
            }

            $product = Product::findOrFail($stockItem->product_id);

            RepairPart::create([
                'repair_id' => $repair->id,
                'stock_item_id' => $stockItem->id,
                'product_id' => $product->id,
                'unit_cost' => $stockItem->cost_price,
            ]);

            $stockItem->update(['status' => 'consumed']);

            $totalPartsCost += (float) $stockItem->cost_price;
        }

        $repair->updateQuietly(['parts_cost' => $totalPartsCost]);
    }

    public function completeRepair(Repair $repair): Repair
    {
        return DB::transaction(function () use ($repair) {
            $repair->update(['status' => 'completed']);
            return $repair;
        });
    }

    public function cancelRepair(Repair $repair): Repair
    {
        return DB::transaction(function () use ($repair) {
            $stockItemIds = $repair->repairParts()->pluck('stock_item_id');

            StockItem::whereIn('id', $stockItemIds)
                ->where('status', 'consumed')
                ->update(['status' => 'available']);

            $repair->repairParts()->delete();

            $repair->update([
                'status' => 'cancelled',
                'parts_cost' => 0,
            ]);

            return $repair;
        });
    }
}
