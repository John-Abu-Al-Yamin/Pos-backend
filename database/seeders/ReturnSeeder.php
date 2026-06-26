<?php

namespace Database\Seeders;

use App\Models\Returns;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\User;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReturnSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@pos.com')->first();
        $customers = Customer::all()->keyBy('name');

        // Return 1: Emma Williams returns Pixel 8 Pro from Sale 3 (defective camera)
        $sale3 = Sale::where('date', '2026-04-15')
            ->where('customer_id', $customers['Emma Williams']->id)
            ->first();

        if ($sale3) {
            $return1 = Returns::create([
                'sale_id' => $sale3->id,
                'customer_id' => $customers['Emma Williams']->id,
                'user_id' => $admin->id,
                'return_date' => '2026-04-18',
                'refund_method' => 'cash',
                'refund_total' => 949.00,
                'restocking_fee' => 50.00,
                'reason' => 'Defective camera - blurry photos in all modes',
                'notes' => 'Customer reported issue within 3 days of purchase. Camera module confirmed faulty during inspection.',
                'reference_code' => 'RET-20260418-0001',
            ]);

            $saleItem = $sale3->saleItems()
                ->whereHas('product', fn($q) => $q->where('name', 'Pixel 8 Pro'))
                ->first();

            if ($saleItem) {
                $stockItem = $saleItem->stockItems()->first();

                ReturnItem::create([
                    'return_id' => $return1->id,
                    'sale_item_id' => $saleItem->id,
                    'stock_item_id' => $stockItem?->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => 1,
                    'refund_amount' => 999.00,
                    'condition_after_inspection' => 'fair',
                    'restock' => true,
                    'reason' => 'Camera not focusing - hardware defect',
                    'notes' => 'Device inspected, camera module needs replacement. Restocked after repair.',
                ]);

                if ($stockItem) {
                    $stockItem->status = 'returned';
                    $stockItem->notes = 'Returned - camera defect. Inspected and repaired.';
                    $stockItem->save();
                }
            }
        }

        // Return 2: Michael Brown returns iPhone 15 Pro Max from Sale 4 (changed mind)
        $sale4 = Sale::where('date', '2026-04-20')
            ->where('customer_id', $customers['Michael Brown']->id)
            ->first();

        if ($sale4) {
            $return2 = Returns::create([
                'sale_id' => $sale4->id,
                'customer_id' => $customers['Michael Brown']->id,
                'user_id' => $admin->id,
                'return_date' => '2026-04-22',
                'refund_method' => 'card',
                'refund_total' => 1299.00,
                'restocking_fee' => 0.00,
                'reason' => 'Customer changed mind - decided to keep current phone',
                'notes' => 'Full refund within 14-day return window. Device in perfect condition.',
                'reference_code' => 'RET-20260422-0001',
            ]);

            $saleItem = $sale4->saleItems()->first();

            if ($saleItem) {
                $stockItem = $saleItem->stockItems()->first();

                ReturnItem::create([
                    'return_id' => $return2->id,
                    'sale_item_id' => $saleItem->id,
                    'stock_item_id' => $stockItem?->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => 1,
                    'refund_amount' => 1299.00,
                    'condition_after_inspection' => 'new',
                    'restock' => true,
                    'reason' => 'Change of mind - unopened condition',
                    'notes' => 'Device sealed in original box, no signs of use.',
                ]);

                if ($stockItem) {
                    $stockItem->status = 'available';
                    $stockItem->notes = 'Returned - change of mind. Restocked as new.';
                    $stockItem->save();
                }
            }
        }

        // Return 3: Sarah Johnson returns iPhone 15 from Sale 1 (screen issue)
        $sale1 = Sale::where('date', '2026-04-05')
            ->where('customer_id', $customers['Sarah Johnson']->id)
            ->first();

        if ($sale1) {
            $return3 = Returns::create([
                'sale_id' => $sale1->id,
                'customer_id' => $customers['Sarah Johnson']->id,
                'user_id' => $admin->id,
                'return_date' => '2026-05-01',
                'refund_method' => 'bank_transfer',
                'refund_total' => 974.00,
                'restocking_fee' => 25.00,
                'reason' => 'Screen flickering intermittently after 3 weeks of use',
                'notes' => 'Screen issue confirmed. Device out of 14-day window, charged 25 restocking fee.',
                'reference_code' => 'RET-20260501-0001',
            ]);

            $saleItem = $sale1->saleItems()
                ->whereHas('product', fn($q) => $q->where('name', 'iPhone 15'))
                ->first();

            if ($saleItem) {
                $stockItem = $saleItem->stockItems()->first();

                ReturnItem::create([
                    'return_id' => $return3->id,
                    'sale_item_id' => $saleItem->id,
                    'stock_item_id' => $stockItem?->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => 1,
                    'refund_amount' => 999.00,
                    'condition_after_inspection' => 'damaged',
                    'restock' => false,
                    'reason' => 'Screen flickering - hardware failure',
                    'notes' => 'Display assembly faulty. Device marked as damaged, returned to supplier.',
                ]);

                if ($stockItem) {
                    $stockItem->status = 'damaged';
                    $stockItem->notes = 'Returned - screen flickering. Sent to supplier for warranty.';
                    $stockItem->save();
                }
            }
        }
    }
}
