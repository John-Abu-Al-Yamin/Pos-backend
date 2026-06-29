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

        // Return 1: مريم علي ترجع Pixel 8 Pro من البيعة 3 (كاميرا معطلة)
        $sale3 = Sale::where('date', '2026-04-15')
            ->where('customer_id', $customers['مريم علي']->id)
            ->first();

        if ($sale3) {
            $return1 = Returns::create([
                'sale_id' => $sale3->id,
                'customer_id' => $customers['مريم علي']->id,
                'user_id' => $admin->id,
                'return_date' => '2026-04-18',
                'refund_method' => 'cash',
                'refund_total' => 949.00,
                'refund_processed_at' => '2026-04-18 17:00:00',
                'restocking_fee' => 50.00,
                'reason' => 'كاميرا معطلة - صور ضبابية في جميع الأوضاع',
                'notes' => 'أبلغ العميل عن المشكلة خلال 3 أيام من الشراء. تم تأكيد عطل وحدة الكاميرا أثناء الفحص.',
                'reference_code' => 'RET-20260418-0001',
            ]);

            $saleItem = $sale3->saleItems()
                ->whereHas('product', fn($q) => $q->where('name', 'Pixel 8 Pro'))
                ->first();

            if ($saleItem) {
                $stockItem = $saleItem->stockItems()->first();

                $unitCost = (float) ($stockItem?->cost_price ?? 0);
                $unitPrice = (float) $saleItem->unit_price;

                ReturnItem::create([
                    'return_id' => $return1->id,
                    'sale_item_id' => $saleItem->id,
                    'stock_item_id' => $stockItem?->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => 1,
                    'refund_amount' => 999.00,
                    'unit_cost' => $unitCost,
                    'total_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'condition_after_inspection' => 'fair',
                    'restock' => true,
                    'reason' => 'الكاميرا لا تركز - عطل في الجهاز',
                    'notes' => 'تم فحص الجهاز، وحدة الكاميرا تحتاج استبدال. تم إعادة التخزين بعد الإصلاح.',
                ]);

                if ($stockItem) {
                    $stockItem->status = 'returned';
                    $stockItem->notes = 'مرتجع - عطل في الكاميرا. تم الفحص والإصلاح.';
                    $stockItem->save();
                }
            }
        }

        // Return 2: ميخائيل إبراهيم يرجع iPhone 15 Pro Max من البيعة 4 (تغيير رأي)
        $sale4 = Sale::where('date', '2026-04-20')
            ->where('customer_id', $customers['ميخائيل إبراهيم']->id)
            ->first();

        if ($sale4) {
            $return2 = Returns::create([
                'sale_id' => $sale4->id,
                'customer_id' => $customers['ميخائيل إبراهيم']->id,
                'user_id' => $admin->id,
                'return_date' => '2026-04-22',
                'refund_method' => 'card',
                'refund_total' => 1299.00,
                'refund_processed_at' => '2026-04-22 16:00:00',
                'restocking_fee' => 0.00,
                'reason' => 'العميل غير رأيه - قرر الاحتفاظ بهاتفه الحالي',
                'notes' => 'استرداد كامل خلال فترة الإرجاع 14 يوماً. الجهاز بحالة ممتازة.',
                'reference_code' => 'RET-20260422-0001',
            ]);

            $saleItem = $sale4->saleItems()->first();

            if ($saleItem) {
                $stockItem = $saleItem->stockItems()->first();

                $unitCost = (float) ($stockItem?->cost_price ?? 0);
                $unitPrice = (float) $saleItem->unit_price;

                ReturnItem::create([
                    'return_id' => $return2->id,
                    'sale_item_id' => $saleItem->id,
                    'stock_item_id' => $stockItem?->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => 1,
                    'refund_amount' => 1299.00,
                    'unit_cost' => $unitCost,
                    'total_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'condition_after_inspection' => 'new',
                    'restock' => true,
                    'reason' => 'تغيير رأي - الجهاز لم يفتح',
                    'notes' => 'الجهاز مغلف في العلبة الأصلية، لا توجد علامات استخدام.',
                ]);

                if ($stockItem) {
                    $stockItem->status = 'available';
                    $stockItem->notes = 'مرتجع - تغيير رأي. تم إعادة التخزين كجهاز جديد.';
                    $stockItem->save();
                }
            }
        }

        // Return 3: سارة أحمد ترجع iPhone 15 من البيعة 1 (مشكلة شاشة)
        $sale1 = Sale::where('date', '2026-04-05')
            ->where('customer_id', $customers['سارة أحمد']->id)
            ->first();

        if ($sale1) {
            $return3 = Returns::create([
                'sale_id' => $sale1->id,
                'customer_id' => $customers['سارة أحمد']->id,
                'user_id' => $admin->id,
                'return_date' => '2026-05-01',
                'refund_method' => 'bank_transfer',
                'refund_total' => 974.00,
                'refund_processed_at' => '2026-05-01 14:00:00',
                'restocking_fee' => 25.00,
                'reason' => 'وميض الشاشة بشكل متقطع بعد 3 أسابيع من الاستخدام',
                'notes' => 'تم تأكيد مشكلة الشاشة. الجهاز خارج فترة 14 يوماً، تم فرض رسوم إعادة تخزين 25.',
                'reference_code' => 'RET-20260501-0001',
            ]);

            $saleItem = $sale1->saleItems()
                ->whereHas('product', fn($q) => $q->where('name', 'iPhone 15'))
                ->first();

            if ($saleItem) {
                $stockItem = $saleItem->stockItems()->first();

                $unitCost = (float) ($stockItem?->cost_price ?? 0);
                $unitPrice = (float) $saleItem->unit_price;

                ReturnItem::create([
                    'return_id' => $return3->id,
                    'sale_item_id' => $saleItem->id,
                    'stock_item_id' => $stockItem?->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => 1,
                    'refund_amount' => 999.00,
                    'unit_cost' => $unitCost,
                    'total_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'condition_after_inspection' => 'damaged',
                    'restock' => false,
                    'reason' => 'وميض الشاشة - عطل في الجهاز',
                    'notes' => 'تجميعة الشاشة معطلة. تم وضع علامة تالف على الجهاز، وإعادته للمورد.',
                ]);

                if ($stockItem) {
                    $stockItem->status = 'damaged';
                    $stockItem->notes = 'مرتجع - وميض الشاشة. تم الإرسال للمورد للضمان.';
                    $stockItem->save();
                }
            }
        }
    }
}
