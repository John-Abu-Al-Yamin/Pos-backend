<?php

namespace App\Services\Maintenance;

use App\Models\InventoryQuantity;
use App\Models\MaintenanceHeader;
use App\Models\MaintenanceUsedPart;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogService;
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
        if (! $header->isEditable()) {
            throw new DomainException('لا يمكن إضافة قطع غيار لتذكرة مكتملة أو ملغاة.');
        }

        $part = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($header, $data) {
            $product = Product::findOrFail($data['product_id']);

            $inventory = InventoryQuantity::where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory || $inventory->quantity < $data['quantity']) {
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
        }));

        app(AuditLogService::class)->record(
            module: 'maintenance',
            action: 'spare_parts_used',
            auditable: $part,
            metadata: [
                'reference_number' => $header->ticket_number,
                'maintenance_header_id' => $header->id,
                'product_id' => $part->product_id,
                'quantity' => (float) $part->quantity,
                'unit_price' => (float) $part->unit_price,
                'total_price' => (float) $part->total_price,
            ],
            severity: 'critical',
        );

        app(AuditLogService::class)->record(
            module: 'inventory',
            action: 'stock_adjusted',
            auditable: $part,
            metadata: [
                'reason' => 'spare_parts_used',
                'reference_number' => $header->ticket_number,
                'movement' => 'out',
                'product_id' => $part->product_id,
                'quantity' => (float) $part->quantity,
                'unit_cost' => (float) $part->cost_price,
            ],
            severity: 'critical',
        );

        return $part;
    }

    public function updatePart(MaintenanceHeader $header, MaintenanceUsedPart $part, array $data): MaintenanceUsedPart
    {
        if (! $header->isEditable()) {
            throw new DomainException('لا يمكن تعديل قطع غيار لتذكرة مكتملة أو ملغاة.');
        }

        $oldQuantity = (float) $part->quantity;

        $updatedPart = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($header, $part, $data) {
            $newQuantity = (float) $data['quantity'];
            $oldQuantity = (float) $part->quantity;
            $delta = $newQuantity - $oldQuantity;

            if ($delta > 0) {
                $inventory = InventoryQuantity::where('product_id', $part->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || $inventory->quantity < $delta) {
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

            if ($delta !== 0.0) {
                StockMovement::create([
                    'product_id' => $part->product_id,
                    'inventory_item_id' => null,
                    'movement_type' => 'stock_adjustment',
                    'movement' => $delta > 0 ? 'out' : 'in',
                    'quantity' => abs($delta),
                    'unit_cost' => (float) $part->cost_price,
                    'reference_type' => MaintenanceUsedPart::class,
                    'reference_id' => $part->id,
                    'notes' => 'Maintenance spare part quantity correction',
                    'created_by' => auth()->id(),
                ]);
            }

            $header->recalculateTotalCost();

            return $part->fresh()->load('product');
        }));

        $newQuantity = (float) $updatedPart->quantity;
        $delta = $newQuantity - $oldQuantity;

        app(AuditLogService::class)->record(
            module: 'maintenance',
            action: 'spare_parts_updated',
            auditable: $updatedPart,
            oldValues: ['quantity' => $oldQuantity],
            newValues: ['quantity' => $newQuantity],
            changedFields: ['quantity'],
            metadata: [
                'reference_number' => $header->ticket_number,
                'maintenance_header_id' => $header->id,
                'product_id' => $updatedPart->product_id,
                'quantity_delta' => $delta,
                'total_price' => (float) $updatedPart->total_price,
            ],
            severity: $delta !== 0.0 ? 'critical' : 'info',
        );

        if ($delta !== 0.0) {
            app(AuditLogService::class)->record(
                module: 'inventory',
                action: 'stock_adjusted',
                auditable: $updatedPart,
                metadata: [
                    'reason' => 'spare_parts_updated',
                    'reference_number' => $header->ticket_number,
                    'movement' => $delta > 0 ? 'out' : 'in',
                    'product_id' => $updatedPart->product_id,
                    'quantity' => abs($delta),
                    'unit_cost' => (float) $updatedPart->cost_price,
                ],
                severity: 'critical',
            );
        }

        return $updatedPart;
    }

    public function removePart(MaintenanceHeader $header, MaintenanceUsedPart $part): void
    {
        if (! $header->isEditable()) {
            throw new DomainException('لا يمكن حذف قطع غيار من تذكرة مكتملة أو ملغاة.');
        }

        $removedPart = $part->replicate();
        $removedPart->setAttribute($part->getKeyName(), $part->getKey());
        $removedPart->exists = true;

        AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($header, $part) {
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
        }));

        app(AuditLogService::class)->record(
            module: 'maintenance',
            action: 'spare_parts_removed',
            auditable: $removedPart,
            metadata: [
                'reference_number' => $header->ticket_number,
                'maintenance_header_id' => $header->id,
                'product_id' => $removedPart->product_id,
                'quantity' => (float) $removedPart->quantity,
                'total_price' => (float) $removedPart->total_price,
            ],
            severity: 'warning',
        );

        app(AuditLogService::class)->record(
            module: 'inventory',
            action: 'stock_adjusted',
            auditable: $removedPart,
            metadata: [
                'reason' => 'spare_parts_removed',
                'reference_number' => $header->ticket_number,
                'movement' => 'in',
                'product_id' => $removedPart->product_id,
                'quantity' => (float) $removedPart->quantity,
                'unit_cost' => (float) $removedPart->cost_price,
            ],
            severity: 'critical',
        );
    }

    public function returnAllParts(MaintenanceHeader $header): void
    {
        $returnedParts = $header->usedParts->map(fn (MaintenanceUsedPart $part) => [
            'product_id' => $part->product_id,
            'quantity' => (float) $part->quantity,
            'unit_cost' => (float) $part->cost_price,
        ])->values()->all();

        AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($header) {
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
        }));

        if ($returnedParts !== []) {
            app(AuditLogService::class)->record(
                module: 'inventory',
                action: 'stock_adjusted',
                auditable: $header,
                metadata: [
                    'reason' => 'maintenance_parts_returned',
                    'reference_number' => $header->ticket_number,
                    'movement' => 'in',
                    'items' => $returnedParts,
                ],
                severity: 'critical',
            );
        }
    }
}
