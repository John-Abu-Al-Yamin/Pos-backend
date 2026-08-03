<?php

namespace App\Services\Maintenance;

use App\Models\InventoryQuantity;
use App\Models\MaintenanceDevice;
use App\Models\MaintenanceHeader;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class MaintenanceTicketService
{
    public function createTicket(array $data): MaintenanceHeader
    {
        $header = AuditLogService::withoutModelEvents(fn () => DB::transaction(function () use ($data) {
            $deviceId = $data['maintenance_device_id'] ?? null;

            if (! $deviceId) {
                $deviceData = [
                    'product_id' => $data['product_id'] ?? null,
                    'device_type' => $data['device_type'] ?? null,
                    'brand' => $data['brand'] ?? null,
                    'model' => $data['model'] ?? null,
                    'serial_number' => $data['serial_number'] ?? null,
                    'color' => $data['color'] ?? null,
                    'condition_notes' => $data['condition_notes'] ?? null,
                ];
                $device = MaintenanceDevice::create($deviceData);
                $deviceId = $device->id;
            }

            $headerData = [
                'maintenance_device_id' => $deviceId,
                'customer_id' => $data['customer_id'] ?? null,
                'ticket_number' => $this->generateTicketNumber(),
                'status' => 'pending',
                'problem_description' => $data['problem_description'],
                'received_date' => $data['received_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'total_cost' => $data['total_cost'] ?? 0,
                'advance_payment' => $data['advance_payment'] ?? 0,
                'created_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ];

            $header = MaintenanceHeader::create($headerData);

            return $header->load([
                'maintenanceDevice.product',
                'customer',
                'createdBy',
            ])->loadSum('operations', 'cost')
                ->loadSum('usedParts', 'total_price');
        }));

        app(AuditLogService::class)->record(
            module: 'maintenance',
            action: 'maintenance_created',
            auditable: $header,
            metadata: [
                'reference_number' => $header->ticket_number,
                'customer_id' => $header->customer_id,
                'maintenance_device_id' => $header->maintenance_device_id,
                'status' => $header->status,
                'advance_payment' => (float) $header->advance_payment,
                'total_cost' => (float) $header->total_cost,
                'reason' => $header->problem_description,
            ],
        );

        return $header;
    }

    public function deletePending(MaintenanceHeader $header): void
    {
        if (! $header->isPending()) {
            throw new \DomainException('Only pending maintenance tickets can be deleted.');
        }

        $header = MaintenanceHeader::with([
            'maintenanceDevice.product',
            'customer',
            'operations',
            'usedParts.product',
        ])->findOrFail($header->id);

        $snapshot = [
            'maintenance' => $header->toArray(),
            'operations' => $header->operations->map(fn ($operation) => [
                'id' => $operation->id,
                'description' => $operation->description,
                'operation_date' => optional($operation->operation_date)->toDateString(),
                'technician' => $operation->technician,
                'cost' => (float) $operation->cost,
                'notes' => $operation->notes,
            ])->values()->all(),
            'used_parts' => $header->usedParts->map(fn ($part) => [
                'id' => $part->id,
                'product_id' => $part->product_id,
                'product_name' => $part->product?->name,
                'quantity' => (float) $part->quantity,
                'cost_price' => (float) $part->cost_price,
                'unit_price' => (float) $part->unit_price,
                'total_price' => (float) $part->total_price,
            ])->values()->all(),
        ];

        $restoredParts = $snapshot['used_parts'];

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
                    'notes' => 'Stock restored before maintenance ticket deletion',
                    'created_by' => auth()->id(),
                ]);
            }

            $header->usedParts()->delete();
            $header->operations()->delete();
            $header->delete();
        }));

        app(AuditLogService::class)->record(
            module: 'maintenance',
            action: 'maintenance_deleted',
            auditable: $header,
            oldValues: $snapshot,
            metadata: [
                'reference_number' => $header->ticket_number,
                'customer_id' => $header->customer_id,
                'total_cost' => (float) $header->total_cost,
                'operations_count' => count($snapshot['operations']),
                'used_parts_count' => count($snapshot['used_parts']),
                'snapshot' => $snapshot,
            ],
            severity: 'warning',
        );

        if ($restoredParts !== []) {
            app(AuditLogService::class)->record(
                module: 'inventory',
                action: 'stock_adjusted',
                auditable: $header,
                metadata: [
                    'reason' => 'maintenance_deleted',
                    'reference_number' => $header->ticket_number,
                    'movement' => 'in',
                    'items' => $restoredParts,
                ],
                severity: 'critical',
            );
        }
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'MNT-'.now()->format('YmdHis').str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        } while (MaintenanceHeader::where('ticket_number', $number)->exists());

        return $number;
    }
}
