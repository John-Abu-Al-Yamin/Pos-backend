<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceDevice;
use App\Models\MaintenanceHeader;
use Illuminate\Support\Facades\DB;

class MaintenanceTicketService
{
    public function createTicket(array $data): MaintenanceHeader
    {
        return DB::transaction(function () use ($data) {
            $deviceId = $data['maintenance_device_id'] ?? null;

            if (!$deviceId) {
                $deviceData = [
                    'product_id' => $data['product_id'] ?? null,
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
        });
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'MNT-' . now()->format('YmdHis') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        } while (MaintenanceHeader::where('ticket_number', $number)->exists());

        return $number;
    }
}
