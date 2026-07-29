<?php

namespace App\Services\Reports;

use App\Models\MaintenanceHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MaintenanceReportService
{
    public function generate(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
        $status = $filters['status'] ?? null;
        $customerId = $filters['customer_id'] ?? null;

        return [
            'date_basis' => [
                'ticket_metrics' => 'received_date',
                'revenue_metrics' => 'delivery_date',
                'used_parts_details' => 'delivery_date for delivered tickets, repaired_at for repaired tickets',
            ],
            'summary' => $this->getSummary($dateFrom, $dateTo, $status, $customerId),
            'by_device_type' => $this->getByDeviceType($dateFrom, $dateTo, $status, $customerId),
            'by_period' => $this->getByPeriod($dateFrom, $dateTo, $filters['group_by'] ?? 'day', $status, $customerId),
            'used_parts_details' => $this->getUsedPartsDetails($dateFrom, $dateTo, $status, $customerId),
        ];
    }

    public function getSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $status = null,
        ?int $customerId = null,
    ): array {
        $dateRange = [$dateFrom->toDateString(), $dateTo->toDateString()];

        $totalQuery = DB::table('maintenance_headers')
            ->whereBetween('maintenance_headers.received_date', $dateRange);

        if ($status) {
            $this->applyStatusFilter($totalQuery, $status);
        } else {
            $totalQuery->where('maintenance_headers.status', '!=', 'cancelled');
        }

        if ($customerId) {
            $totalQuery->where('maintenance_headers.customer_id', $customerId);
        }

        $received = (object) (clone $totalQuery)
            ->selectRaw('
                COUNT(*) as received_count,
                COALESCE(SUM(CASE WHEN maintenance_headers.status = ? THEN 1 ELSE 0 END), 0) as cancelled_count
            ', ['cancelled'])
            ->first();

        $total = (object) (clone $totalQuery)
            ->selectRaw('COUNT(*) as total_tickets')
            ->first();

        $deliveredQuery = DB::table('maintenance_headers')
            ->whereBetween('maintenance_headers.delivery_date', $dateRange);

        $this->applyRevenueStatusFilter($deliveredQuery, $status);

        if ($customerId) {
            $deliveredQuery->where('maintenance_headers.customer_id', $customerId);
        }

        $delivered = (object) (clone $deliveredQuery)
            ->selectRaw('
                COUNT(*) as delivered_count,
                COALESCE(SUM(maintenance_headers.total_cost), 0) as total_revenue,
                COALESCE(SUM(maintenance_headers.advance_payment), 0) as total_advance
            ')
            ->first();

        $partsQuery = DB::table('maintenance_used_parts')
            ->join('maintenance_headers', 'maintenance_headers.id', '=', 'maintenance_used_parts.maintenance_header_id')
            ->whereBetween('maintenance_headers.delivery_date', $dateRange);

        $this->applyRevenueStatusFilter($partsQuery, $status);

        if ($customerId) {
            $partsQuery->where('maintenance_headers.customer_id', $customerId);
        }

        $parts = (object) (clone $partsQuery)
            ->selectRaw('
                COALESCE(SUM(maintenance_used_parts.total_price), 0) as parts_revenue,
                COALESCE(SUM(maintenance_used_parts.cost_price * maintenance_used_parts.quantity), 0) as parts_cost
            ')
            ->first();

        $totalRevenue = (float) $delivered->total_revenue;
        $partsCost = (float) $parts->parts_cost;
        $partsRevenue = (float) $parts->parts_revenue;
        $laborRevenue = $totalRevenue - $partsRevenue;
        $maintenanceProfit = $totalRevenue - $partsCost;

        return [
            'date_basis' => [
                'received_tickets' => 'received_date',
                'revenue' => 'delivery_date',
            ],
            'total_tickets' => (int) $total->total_tickets,
            'received_tickets' => (int) $received->received_count,
            'delivered_tickets' => (int) $delivered->delivered_count,
            'total_labor_revenue' => round(max($laborRevenue, 0), 2),
            'total_parts_revenue' => $partsRevenue,
            'total_revenue' => $totalRevenue,
            'total_parts_cost' => $partsCost,
            'gross_profit' => round($maintenanceProfit, 2),
            'total_advance_payment' => (float) $delivered->total_advance,
        ];
    }

    public function getByDeviceType(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $status = null,
        ?int $customerId = null,
    ): array {
        $dateRange = [$dateFrom->toDateString(), $dateTo->toDateString()];

        $query = DB::table('maintenance_headers')
            ->join('maintenance_devices', 'maintenance_devices.id', '=', 'maintenance_headers.maintenance_device_id')
            ->whereBetween('maintenance_headers.delivery_date', $dateRange)
            ->selectRaw('
                maintenance_devices.device_type,
                COALESCE(SUM(maintenance_headers.total_cost), 0) as total_revenue,
                COUNT(*) as ticket_count
            ')
            ->groupBy('maintenance_devices.device_type');

        $this->applyRevenueStatusFilter($query, $status);

        if ($customerId) {
            $query->where('maintenance_headers.customer_id', $customerId);
        }

        return $query->get()->map(function ($item) {
            return [
                'device_type' => $item->device_type,
                'ticket_count' => (int) $item->ticket_count,
                'total_revenue' => (float) $item->total_revenue,
            ];
        })->toArray();
    }

    public function getByPeriod(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $groupBy = 'day',
        ?string $status = null,
        ?int $customerId = null,
    ): array {
        $dateRange = [$dateFrom->toDateString(), $dateTo->toDateString()];
        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'year' => '%Y',
            'week' => '%x-%v',
            default => '%Y-%m-%d',
        };

        $query = DB::table('maintenance_headers')
            ->whereBetween('maintenance_headers.delivery_date', $dateRange)
            ->selectRaw("
                DATE_FORMAT(maintenance_headers.delivery_date, '$dateFormat') as period,
                COUNT(*) as ticket_count,
                COALESCE(SUM(maintenance_headers.total_cost), 0) as total_revenue
            ")
            ->groupBy('period')
            ->orderBy('period');

        $this->applyRevenueStatusFilter($query, $status);

        if ($customerId) {
            $query->where('maintenance_headers.customer_id', $customerId);
        }

        return $query->get()->map(function ($item) {
            return [
                'period' => $item->period,
                'ticket_count' => (int) $item->ticket_count,
                'total_revenue' => (float) $item->total_revenue,
            ];
        })->toArray();
    }

    public function getUsedPartsDetails(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $status = null,
        ?int $customerId = null,
    ): array {
        $query = DB::table('maintenance_used_parts')
            ->join('maintenance_headers', 'maintenance_headers.id', '=', 'maintenance_used_parts.maintenance_header_id')
            ->join('products', 'products.id', '=', 'maintenance_used_parts.product_id')
            ->where(function ($query) use ($dateFrom, $dateTo) {
                $query->where(function ($delivered) use ($dateFrom, $dateTo) {
                    $delivered->where('maintenance_headers.status', 'delivered')
                        ->whereBetween('maintenance_headers.delivery_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
                })->orWhere(function ($repaired) use ($dateFrom, $dateTo) {
                    $repaired->where('maintenance_headers.status', 'repaired')
                        ->whereBetween('maintenance_headers.repaired_at', [$dateFrom, $dateTo]);
                });
            })
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                COALESCE(SUM(maintenance_used_parts.quantity), 0) as total_quantity,
                COALESCE(SUM(maintenance_used_parts.cost_price * maintenance_used_parts.quantity), 0) as total_cost,
                COALESCE(SUM(maintenance_used_parts.total_price), 0) as total_revenue
            ')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity');

        $this->applyUsedPartsStatusFilter($query, $status);

        if ($customerId) {
            $query->where('maintenance_headers.customer_id', $customerId);
        }

        return $query->get()->map(function ($item) {
            return [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'total_quantity' => (float) $item->total_quantity,
                'total_cost' => (float) $item->total_cost,
                'total_revenue' => (float) $item->total_revenue,
            ];
        })->toArray();
    }

    public function getOperationalSummary(Carbon $dateFrom, Carbon $dateTo): array
    {
        $statuses = DB::table('maintenance_headers')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        return [
            'status_counts' => $statuses,
            'pending' => (int) ($statuses['pending'] ?? 0),
            'under_repair' => (int) ($statuses['under_repair'] ?? 0),
            'waiting_parts' => (int) ($statuses['waiting_parts'] ?? 0),
            'repaired_not_delivered' => (int) ($statuses['repaired'] ?? 0),
            'received_in_period' => (int) DB::table('maintenance_headers')
                ->whereBetween('received_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->where('status', '!=', 'cancelled')
                ->count(),
            'delivered_in_period' => (int) DB::table('maintenance_headers')
                ->whereBetween('delivery_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->where('status', 'delivered')
                ->count(),
        ];
    }

    public function getRecentTickets(int $limit = 5, bool $includeFinancials = true): array
    {
        return MaintenanceHeader::with('customer:id,name', 'maintenanceDevice:id,device_type,brand,model')
            ->latest()
            ->limit($limit)
            ->get(['id', 'ticket_number', 'customer_id', 'maintenance_device_id', 'status', 'total_cost', 'created_at'])
            ->map(function (MaintenanceHeader $ticket) use ($includeFinancials) {
                $item = [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'customer_name' => $ticket->customer?->name,
                    'device' => $ticket->maintenanceDevice ? trim(implode(' ', array_filter([
                        $ticket->maintenanceDevice->brand,
                        $ticket->maintenanceDevice->model,
                    ]))) ?: $ticket->maintenanceDevice->device_type : null,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at?->toIso8601String(),
                ];

                if ($includeFinancials) {
                    $item['total_cost'] = (float) $ticket->total_cost;
                }

                return $item;
            })
            ->toArray();
    }

    private function applyStatusFilter($query, ?string $status): void
    {
        if ($status) {
            $query->where('maintenance_headers.status', $status);
        }
    }

    private function applyRevenueStatusFilter($query, ?string $status): void
    {
        if ($status) {
            $query->where('maintenance_headers.status', $status);
            return;
        }

        $query->where('maintenance_headers.status', 'delivered');
    }

    private function applyUsedPartsStatusFilter($query, ?string $status): void
    {
        if ($status) {
            $query->where('maintenance_headers.status', $status);
        }
    }
}
