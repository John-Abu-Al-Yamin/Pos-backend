<?php

namespace Database\Seeders;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryAdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@pos.com')->first();

        $adjustments = [
            // 1: Galaxy S24 damaged in transit
            [
                'product_name' => 'Galaxy S24',
                'qty_to_remove' => 1,
                'reason' => 'Damaged',
                'notes' => 'تلف في الشاشة أثناء النقل - تم إزالة الجهاز من المخزون',
                'created_by' => $admin->id,
            ],
            // 2: Accessories damaged
            [
                'product_name' => 'حامي شاشة زجاجي مقسى',
                'qty_to_remove' => 3,
                'reason' => 'Broken',
                'notes' => 'وصلت مكسورة من المورد - تم إزالتها من المخزون',
                'created_by' => $admin->id,
            ],
            // 3: Internal use
            [
                'product_name' => 'جراب هاتف سيليكون',
                'qty_to_remove' => 1,
                'reason' => 'Internal use',
                'notes' => 'استخدام داخلي - جراب هاتف للمعرض',
                'created_by' => $admin->id,
            ],
            // 4: Voided — discrepancy during inventory count
            [
                'product_name' => 'شاحن سريع USB-C',
                'qty_to_remove' => 2,
                'reason' => 'Count discrepancy',
                'notes' => 'خلل في الجرد - نقص عدد 2 شاحن',
                'created_by' => $admin->id,
            ],
        ];

        foreach ($adjustments as $data) {
            $product = Product::where('name', $data['product_name'])->first();
            if (!$product) {
                continue;
            }

            $quantityBefore = StockItem::where('product_id', $product->id)
                ->where('status', 'available')
                ->count();

            $itemsToRemove = min($data['qty_to_remove'], $quantityBefore);
            if ($itemsToRemove <= 0) {
                continue;
            }

            $availableItems = StockItem::where('product_id', $product->id)
                ->where('status', 'available')
                ->orderBy('id')
                ->take($itemsToRemove)
                ->get();

            $newStatus = match ($data['reason']) {
                'Damaged', 'Broken' => 'damaged',
                'Internal use' => 'consumed',
                default => 'voided',
            };

            foreach ($availableItems as $item) {
                $item->update(['status' => $newStatus]);
            }

            $quantityAfter = $quantityBefore - $itemsToRemove;

            $avgUnitCost = $availableItems->isNotEmpty()
                ? $availableItems->avg('cost_price')
                : 0;

            $totalLoss = round($itemsToRemove * $avgUnitCost, 2);

            InventoryAdjustment::create([
                'product_id' => $product->id,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'difference' => -$itemsToRemove,
                'total_loss_amount' => $totalLoss,
                'total_gain_amount' => 0,
                'unit_cost_snapshot' => round($avgUnitCost, 2),
                'reason' => $data['reason'],
                'notes' => $data['notes'],
                'created_by' => $data['created_by'],
            ]);
        }
    }
}
