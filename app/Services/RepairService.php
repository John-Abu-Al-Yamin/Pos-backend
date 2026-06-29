<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Repair;
use App\Models\RepairPart;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class RepairService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
    ) {}

    public function createRepair(array $data): Repair
    {
        return DB::transaction(function () use ($data) {
            $deposit = (float) ($data['deposit'] ?? 0);
            $depositPaidAt = $deposit > 0 ? now() : null;

            $repair = Repair::create([
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'device_type' => $data['device_type'],
                'device_serial' => $data['device_serial'] ?? null,
                'issue_description' => $data['issue_description'],
                'work_description' => $data['work_description'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? 0,
                'deposit' => $deposit,
                'deposit_paid_at' => $depositPaidAt,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
            ]);

            if ($depositPaidAt) {
                $this->ledger->recordRepairDeposit($repair);
            }

            if (!empty($data['parts'])) {
                $this->attachParts($repair, $data['parts']);
            }

            $repair->load(['customer', 'repairParts.product', 'repairParts.stockItem']);

            return $repair;
        });
    }

    public function updateRepair(Repair $repair, array $data): Repair
    {
        if ($repair->status === 'completed') {
            throw new \RuntimeException('لا يمكن تعديل أمر الإصلاح بعد اكتماله. يجب إلغاء الأمر وإنشاء أمر جديد.');
        }

        return DB::transaction(function () use ($repair, $data) {
            $updatable = [
                'customer_id', 'customer_name', 'customer_phone',
                'device_type', 'device_serial', 'issue_description',
                'work_description', 'estimated_cost', 'deposit',
                'payment_status',
                'expected_delivery_date', 'status',
            ];

            $fillData = [];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $data)) {
                    $fillData[$field] = $data[$field];
                }
            }

            if (($fillData['status'] ?? null) === 'completed') {
                $fillData['completed_at'] = now();
            }

            if (array_key_exists('deposit', $data)) {
                $newDeposit = (float) $data['deposit'];
                $oldDeposit = (float) $repair->deposit;
                $fillData['deposit_paid_at'] = $newDeposit > 0 ? now() : null;
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
                } else {
                    $repair->updateQuietly(['parts_cost' => 0]);
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

    public function completeRepair(Repair $repair, bool $markAsPaid = false): Repair
    {
        if ($repair->status === 'completed') {
            throw new \RuntimeException('أمر الإصلاح مكتمل بالفعل.');
        }

        return DB::transaction(function () use ($repair, $markAsPaid) {
            $finalPayment = max(0, (float) $repair->estimated_cost - (float) $repair->deposit);

            $finalPartsCost = (float) $repair->repairParts()->sum('unit_cost');

            $updates = [
                'status' => 'completed',
                'completed_at' => now(),
                'final_payment' => $finalPayment,
                'final_parts_cost' => $finalPartsCost,
            ];

            if ($markAsPaid) {
                $updates['payment_status'] = 'paid';
                $updates['final_paid_at'] = now();
            }

            $repair->update($updates);

            $this->ledger->recordRepairPartsConsumption($repair, $finalPartsCost);

            if ($markAsPaid && $finalPayment > 0) {
                $this->ledger->recordRepairFinalPayment($repair, $finalPayment);
            }

            return $repair;
        });
    }

    public function payRepair(Repair $repair): Repair
    {
        return DB::transaction(function () use ($repair) {
            $finalPayment = max(0, (float) $repair->estimated_cost - (float) $repair->deposit);

            $repair->update([
                'payment_status' => 'paid',
                'final_paid_at' => now(),
                'final_payment' => $finalPayment,
            ]);

            if ($finalPayment > 0) {
                $this->ledger->recordRepairFinalPayment($repair, $finalPayment);
            }

            return $repair;
        });
    }

    public function voidRepair(Repair $repair, int $userId, string $reason): Repair
    {
        if ($repair->voided_at) {
            throw new \RuntimeException('أمر الإصلاح ملغي بالفعل.');
        }

        return DB::transaction(function () use ($repair, $userId, $reason) {
            $stockItemIds = $repair->repairParts()->pluck('stock_item_id');
            StockItem::whereIn('id', $stockItemIds)
                ->where('status', 'consumed')
                ->update(['status' => 'available']);
            $repair->repairParts()->delete();

            $repair->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
                'completed_at' => null,
                'final_paid_at' => null,
                'final_payment' => 0,
                'parts_cost' => 0,
                'final_parts_cost' => null,
            ]);

            $this->ledger->recordRepairVoidReversal($repair);

            return $repair;
        });
    }

    public function cancelRepair(Repair $repair): Repair
    {
        return DB::transaction(function () use ($repair) {
            $hasDeposit = (float) $repair->deposit > 0;

            if ($hasDeposit) {
                $this->ledger->recordDepositRefund($repair);
            }

            $stockItemIds = $repair->repairParts()->pluck('stock_item_id');
            StockItem::whereIn('id', $stockItemIds)
                ->where('status', 'consumed')
                ->update(['status' => 'available']);
            $repair->repairParts()->delete();

            $repair->update([
                'status' => 'cancelled',
                'parts_cost' => 0,
                'final_parts_cost' => null,
                'completed_at' => null,
                'final_paid_at' => null,
                'final_payment' => 0,
            ]);

            return $repair;
        });
    }
}
