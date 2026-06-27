<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;
use App\Models\StockItem;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    private static int $imeiCounter = 350000000000000;

    public function run(): void
    {
        $techDistrib = Supplier::where('name', 'تيك للتوزيع')->first();
        $mobileWholesale = Supplier::where('name', 'الجملة للجوال')->first();
        $phoneParts = Supplier::where('name', 'قطع الهواتف المباشر')->first();

        // Purchase 1: Opening Stock — Apr 1
        $p1 = PurchaseHeader::create([
            'supplier_id' => $techDistrib->id,
            'date' => '2026-04-01',
            'type' => 'opening_stock',
            'reference' => 'مخزون أولي للربع الثاني 2026',
        ]);
        $this->addItem($p1, 'iPhone 15 Pro Max', 3, 950.00);
        $this->addItem($p1, 'iPhone 15', 3, 750.00);
        $this->addItem($p1, 'iPhone 14', 2, 550.00);
        $this->addItem($p1, 'Galaxy S24 Ultra', 2, 850.00);
        $this->addItem($p1, 'Galaxy S24', 2, 650.00);
        $p1->recalculateTotal();

        // Purchase 2: New stock — May 15
        $p2 = PurchaseHeader::create([
            'supplier_id' => $mobileWholesale->id,
            'date' => '2026-05-15',
            'type' => 'purchase',
            'reference' => 'طلب جملة منتصف الشهر',
        ]);
        $this->addItem($p2, 'Galaxy Z Flip 6', 2, 800.00);
        $this->addItem($p2, 'Pixel 8 Pro', 2, 700.00);
        $this->addItem($p2, 'Pixel 8', 2, 550.00);
        $this->addItem($p2, 'Pixel 7a', 2, 350.00);
        $this->addItem($p2, 'iPhone SE 3rd Gen', 2, 400.00);
        $p2->recalculateTotal();

        // Purchase 3: Accessories — May 20
        $p3 = PurchaseHeader::create([
            'supplier_id' => $phoneParts->id,
            'date' => '2026-05-20',
            'type' => 'purchase',
            'reference' => 'طلب إكسسوارات بالجملة',
        ]);
        $this->addItem($p3, 'شاحن سريع USB-C', 20, 8.00);
        $this->addItem($p3, 'جراب هاتف سيليكون', 15, 3.50);
        $this->addItem($p3, 'حامي شاشة زجاجي مقسى', 30, 2.00);
        $p3->recalculateTotal();

        // Purchase 4: Top-up — Jun 10
        $p4 = PurchaseHeader::create([
            'supplier_id' => $techDistrib->id,
            'date' => '2026-06-10',
            'type' => 'purchase',
            'reference' => 'إعادة تزويد العناصر سريعة الحركة',
        ]);
        $this->addItem($p4, 'iPhone 15 Pro Max', 1, 940.00);
        $this->addItem($p4, 'Galaxy A55', 3, 250.00);
        $p4->recalculateTotal();
    }

    private function addItem(PurchaseHeader $header, string $productName, int $qty, float $unitCost): void
    {
        $product = Product::where('name', $productName)->first();
        $lineTotal = round($qty * $unitCost, 2);

        $purchaseItem = PurchaseItem::create([
            'purchase_header_id' => $header->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'line_total' => $lineTotal,
            'condition' => 'new',
        ]);

        for ($i = 0; $i < $qty; $i++) {
            $data = [
                'product_id' => $product->id,
                'purchase_item_id' => $purchaseItem->id,
                'cost_price' => $unitCost,
                'condition' => 'new',
                'status' => 'available',
            ];

            if ($product->is_serialized) {
                $data['serial_number'] = $this->nextImei();
                $data['battery_health'] = rand(95, 100);
                $data['screen_condition'] = 'perfect';
                $data['body_condition'] = 'perfect';
                $data['face_id_working'] = true;
                $data['fingerprint_working'] = true;
                $data['camera_working'] = true;
                $data['speaker_working'] = true;
                $data['accessories'] = 'شاحن، كابل، مشبك SIM، علبة';
            }

            StockItem::create($data);
        }
    }

    private function nextImei(): string
    {
        return (string) self::$imeiCounter++;
    }
}
