<?php

namespace App\Services\Maintenance;

use App\Models\InventoryQuantity;
use App\Models\MaintenanceHeader;
use App\Models\MaintenanceUsedPart;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Pricing\PricingService;
use DomainException;
use Illuminate\Support\Facades\DB;

class MaintenancePartService
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    public function addPart(MaintenanceHeader $header, array $data): MaintenanceUsedPart
    {
        if (!$header->isEditable()) {
            throw new DomainException('لا يمكن إضافة قطع غيار لتذكرة مكتملة أو ملغاة.');
        }

        return DB::transaction(function () use ($header, $data) {
            $product = Product::findOrFail($data['product_id']);

            $inventory = InventoryQuantity::where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (!$inventory || $inventory->quantity < $data['quantity']) {
                throw new DomainException('الكمية المطلوبة غير متوفرة في المخزون.');
            }

            $pricing = $this->pricingService->calculateSellingPrice($product);

            if ($pricing['cost_price'] <= 0 || $pricing['unit_price'] <= 0) {
                throw new DomainException('لا يمكن تحديد سعر القطعة. تأكد من توفر سعر التكلفة والتسعير.');
            }

            $totalPrice = $data['quantity'] * $pricing['unit_price'];

            $part = MaintenanceUsedPart::create([
                'maintenance_header_id' => $header->id,
                'product_id' => $product->id,
                'quantity' => $data['quantity'],
                'cost_price' => $pricing['cost_price'],
                'unit_price' => $pricing['unit_price'],
                'total_price' => $totalPrice,
            ]);

            $inventory->decrement('quantity', $data['quantity']);

            StockMovement::create([
                'product_id' => $product->id,
                'inventory_item_id' => null,
                'movement_type' => 'repair_usage',
                'movement' => 'out',
                'quantity' => $data['quantity'],
                'unit_cost' => $pricing['cost_price'],
                'reference_type' => MaintenanceUsedPart::class,
                'reference_id' => $part->id,
                'created_by' => auth()->id(),
            ]);

            $header->recalculateTotalCost();

            return $part->load('product');
        });
    }

    public function updatePart(MaintenanceHeader $header, MaintenanceUsedPart $part, array $data): MaintenanceUsedPart
    {
        if (!$header->isEditable()) {
            throw new DomainException('لا يمكن تعديل قطع غيار لتذكرة مكتملة أو ملغاة.');
        }

        return DB::transaction(function () use ($header, $part, $data) {
            $newQuantity = (float) $data['quantity'];
            $oldQuantity = (float) $part->quantity;
            $delta = $newQuantity - $oldQuantity;

            if ($delta > 0) {
                $inventory = InventoryQuantity::where('product_id', $part->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$inventory || $inventory->quantity < $delta) {
                    throw new DomainException('الكمية المطلوبة غير متوفرة في المخزون.');
                }

                $inventory->decrement('quantity', $delta);
            } elseif ($delta < 0) {
                $inventory = InventoryQuantity::where('product_id', $part->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->increment('quantity', abs($delta));
                }
            }

            $totalPrice = $newQuantity * (float) $part->unit_price;

            $part->update([
                'quantity' => $newQuantity,
                'total_price' => $totalPrice,
            ]);

            StockMovement::where('reference_type', MaintenanceUsedPart::class)
                ->where('reference_id', $part->id)
                ->update([
                    'quantity' => $newQuantity,
                ]);

            $header->recalculateTotalCost();

            return $part->fresh()->load('product');
        });
    }

    public function removePart(MaintenanceHeader $header, MaintenanceUsedPart $part): void
    {
        if (!$header->isEditable()) {
            throw new DomainException('لا يمكن حذف قطع غيار من تذكرة مكتملة أو ملغاة.');
        }

        DB::transaction(function () use ($part) {
            $inventory = InventoryQuantity::where('product_id', $part->product_id)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                $inventory->increment('quantity', $part->quantity);
            }

            StockMovement::create([
                'product_id' => $part->product_id,
                'inventory_item_id' => null,
                'movement_type' => 'stock_adjustment',
                'movement' => 'in',
                'quantity' => $part->quantity,
                'unit_cost' => (float) $part->cost_price,
                'reference_type' => MaintenanceUsedPart::class,
                'reference_id' => $part->id,
                'notes' => 'إلغاء استخدام قطعة غيار في الصيانة - return from maintenance',
                'created_by' => auth()->id(),
            ]);

            $part->delete();
            $header->recalculateTotalCost();
        });
    }

    public function returnAllParts(MaintenanceHeader $header): void
    {
        DB::transaction(function () use ($header) {
            foreach ($header->usedParts as $part) {
                $inventory = InventoryQuantity::where('product_id', $part->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->increment('quantity', $part->quantity);
                }

                StockMovement::create([
                    'product_id' => $part->product_id,
                    'inventory_item_id' => null,
                    'movement_type' => 'stock_adjustment',
                    'movement' => 'in',
                    'quantity' => $part->quantity,
                    'unit_cost' => (float) $part->cost_price,
                    'reference_type' => MaintenanceHeader::class,
                    'reference_id' => $header->id,
                    'notes' => 'إرجاع قطع الغيار بسبب إلغاء التذكرة',
                    'created_by' => auth()->id(),
                ]);

                $part->delete();
            }
            $header->recalculateTotalCost();
        });
    }
}
