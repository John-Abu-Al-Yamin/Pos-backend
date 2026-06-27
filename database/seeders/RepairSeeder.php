<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Repair;
use App\Models\RepairPart;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class RepairSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@pos.com')->first();
        $sarah = User::where('email', 'sarah@pos.com')->first();
        $customers = Customer::all()->keyBy('name');

        $repairs = [
            [
                'customer_id' => $customers['سارة أحمد']->id,
                'device_type' => 'iPhone 15 Pro Max',
                'device_serial' => 'IMEI-350000000000100',
                'issue_description' => 'الشاشة لا تعمل بعد سقوط الجهاز - الزجاج الأمامي مكسور بالكامل',
                'work_description' => 'استبدال الشاشة بالكامل واختبار اللمس والسطوع',
                'estimated_cost' => 450.00,
                'deposit' => 200.00,
                'status' => 'completed',
                'payment_status' => 'paid',
                'user_id' => $admin->id,
                'parts' => [
                    ['product_name' => 'طقم شاشة آيفون 15 برو ماكس', 'qty' => 1],
                ],
            ],
            [
                'customer_id' => $customers['جمال سعيد']->id,
                'device_type' => 'Galaxy S24 Ultra',
                'device_serial' => 'IMEI-350000000000200',
                'issue_description' => 'البطارية تفرغ بسرعة كبيرة - تدوم أقل من 3 ساعات',
                'work_description' => 'فحص البطارية واستبدالها إذا لزم الأمر',
                'estimated_cost' => 180.00,
                'deposit' => 50.00,
                'status' => 'completed',
                'payment_status' => 'paid',
                'user_id' => $sarah->id,
                'parts' => [],
            ],
            [
                'customer_id' => $customers['مريم علي']->id,
                'device_type' => 'iPhone 14',
                'device_serial' => 'IMEI-350000000000300',
                'issue_description' => 'منفذ الشحن لا يعمل - الجهاز لا يشحن',
                'work_description' => 'تنظيف منفذ الشحن واستبدال كابل الشحن الداخلي',
                'estimated_cost' => 150.00,
                'deposit' => 75.00,
                'status' => 'in_progress',
                'payment_status' => 'pending',
                'user_id' => $admin->id,
                'parts' => [
                    ['product_name' => 'كابل منفذ شحن USB-C', 'qty' => 1],
                ],
            ],
            [
                'customer_name' => 'خالد العلي',
                'customer_phone' => '0555000111',
                'device_type' => 'Pixel 8 Pro',
                'device_serial' => 'IMEI-350000000000400',
                'issue_description' => 'كاميرا الخلفية لا تركز - الصور ضبابية باستمرار',
                'work_description' => 'فحص الكاميرا واستبدال وحدة الكاميرا',
                'estimated_cost' => 350.00,
                'deposit' => 100.00,
                'status' => 'completed',
                'payment_status' => 'paid',
                'user_id' => $admin->id,
                'parts' => [
                    ['product_name' => 'كاميرا آيفون 15 برو ماكس', 'qty' => 1],
                ],
            ],
            [
                'customer_id' => $customers['وليد حسن']->id,
                'device_type' => 'Galaxy A55',
                'device_serial' => null,
                'issue_description' => 'مكبر الصوت لا يصدر صوتاً - سماعة المكالمات تعمل',
                'work_description' => 'فحص السماعة الخارجية واستبدالها',
                'estimated_cost' => 80.00,
                'deposit' => 40.00,
                'status' => 'pending',
                'payment_status' => 'pending',
                'user_id' => $sarah->id,
                'parts' => [
                    ['product_name' => 'وحدة سماعة (عام)', 'qty' => 1],
                ],
            ],
            [
                'customer_id' => $customers['ليلى محمد']->id,
                'device_type' => 'iPhone 15',
                'device_serial' => 'IMEI-350000000000500',
                'issue_description' => 'الجهاز لا يفتح بعد تحديث النظام - عالق على شعار Apple',
                'work_description' => 'إعادة تثبيت النظام عبر DFU وحفظ البيانات',
                'estimated_cost' => 120.00,
                'deposit' => 0.00,
                'status' => 'in_progress',
                'payment_status' => 'pending',
                'user_id' => $admin->id,
                'parts' => [],
            ],
            [
                'customer_id' => $customers['سلمى عمر']->id,
                'device_type' => 'Galaxy Z Flip 6',
                'device_serial' => 'IMEI-350000000000600',
                'issue_description' => 'المفصلة تصدر صوتاً عند فتح وإغلاق الجهاز',
                'work_description' => 'فحص المفصلة وتزييتها أو استبدالها',
                'estimated_cost' => 250.00,
                'deposit' => 250.00,
                'status' => 'completed',
                'payment_status' => 'paid',
                'user_id' => $admin->id,
                'parts' => [
                    ['product_name' => 'مفصلة جالاكسي Z فليب 6', 'qty' => 1],
                ],
            ],
            [
                'customer_id' => $customers['دانيال يوسف']->id,
                'device_type' => 'Pixel 7a',
                'device_serial' => 'IMEI-350000000000700',
                'issue_description' => 'شاشة اللمس لا تستجيب في الجزء السفلي',
                'work_description' => 'فحص الشاشة واستبدال طقم الشاشة',
                'estimated_cost' => 200.00,
                'deposit' => 0.00,
                'status' => 'cancelled',
                'payment_status' => 'pending',
                'user_id' => $sarah->id,
                'parts' => [
                    ['product_name' => 'طقم شاشة بكسل 7a', 'qty' => 1],
                ],
            ],
            [
                'customer_name' => 'نور الدين',
                'customer_phone' => '0555000222',
                'device_type' => 'iPhone SE 3rd Gen',
                'device_serial' => 'IMEI-350000000000800',
                'issue_description' => 'زر الصفحة الرئيسية لا يعمل - يحتاج استبدال',
                'work_description' => 'استبدال زر الصفحة الرئيسية',
                'estimated_cost' => 100.00,
                'deposit' => 100.00,
                'status' => 'completed',
                'payment_status' => 'paid',
                'user_id' => $admin->id,
                'parts' => [],
            ],
            [
                'customer_id' => $customers['مريم علي']->id,
                'device_type' => 'iPad Pro 12.9',
                'device_serial' => 'IMEI-350000000000900',
                'issue_description' => 'الجهاز يسخن بشكل غير طبيعي والبطارية تفرغ بسرعة',
                'work_description' => 'فحص البطارية واستبدالها وتنظيف الجهاز داخلياً',
                'estimated_cost' => 280.00,
                'deposit' => 100.00,
                'status' => 'pending',
                'payment_status' => 'pending',
                'user_id' => $sarah->id,
                'parts' => [],
            ],
        ];

        foreach ($repairs as $data) {
            $parts = $data['parts'];
            unset($data['parts']);

            $repair = Repair::create($data);

            $totalPartsCost = 0;

            foreach ($parts as $partData) {
                $product = Product::where('name', $partData['product_name'])->first();
                if (!$product) continue;

                $stockItem = StockItem::where('product_id', $product->id)
                    ->where('status', 'available')
                    ->first();
                if (!$stockItem) continue;

                RepairPart::create([
                    'repair_id' => $repair->id,
                    'stock_item_id' => $stockItem->id,
                    'product_id' => $product->id,
                    'unit_cost' => $stockItem->cost_price,
                ]);

                $stockItem->update(['status' => 'consumed']);

                $totalPartsCost += (float) $stockItem->cost_price;
            }

            if ($totalPartsCost > 0) {
                $repair->updateQuietly(['parts_cost' => $totalPartsCost]);
            }
        }
    }
}
