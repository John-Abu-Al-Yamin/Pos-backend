<?php

namespace App\Services\Dashboard;

class DashboardResponseBuilder
{
    public function kpis(array $current, array $inventory, bool $includeFinancials): array
    {
        $sales = $current['sales'];
        $maintenance = $current['maintenance'];

        $kpis = [
            'sales_net_revenue' => $sales['net_revenue'],
            'sales_invoice_count' => $sales['transaction_count'],
            'average_transaction_value' => $sales['average_transaction_value'],
            'maintenance_revenue' => $maintenance['total_revenue'],
            'maintenance_received_tickets' => $maintenance['received_tickets'],
            'maintenance_delivered_tickets' => $maintenance['delivered_tickets'],
            'low_stock_count' => $inventory['low_stock_count'],
            'out_of_stock_count' => $inventory['out_of_stock_count'],
        ];

        if (!$includeFinancials) {
            return $kpis;
        }

        return array_merge($kpis, [
            'sales_gross_profit' => $sales['gross_profit'],
            'purchase_net_amount' => $current['purchases']['net_purchase_amount'],
            'expenses_paid' => $current['expenses']['paid_amount'],
            'salaries_confirmed' => $current['salaries']['confirmed_amount'],
            'maintenance_profit' => $maintenance['gross_profit'],
            'net_profit' => $current['profitLoss']['net_profit'],
            'inventory_stock_value' => $inventory['total_stock_value'],
        ]);
    }

    public function comparison(array $current, array $previous, bool $includeFinancials): array
    {
        $comparison = [
            'sales_net_revenue' => $this->compare(
                $current['sales']['net_revenue'],
                $previous['sales']['net_revenue'],
            ),
            'sales_invoice_count' => $this->compare(
                $current['sales']['transaction_count'],
                $previous['sales']['transaction_count'],
            ),
            'maintenance_revenue' => $this->compare(
                $current['maintenance']['total_revenue'],
                $previous['maintenance']['total_revenue'],
            ),
            'maintenance_received_tickets' => $this->compare(
                $current['maintenance']['received_tickets'],
                $previous['maintenance']['received_tickets'],
            ),
        ];

        if (!$includeFinancials) {
            return $comparison;
        }

        return array_merge($comparison, [
            'sales_gross_profit' => $this->compare(
                $current['sales']['gross_profit'],
                $previous['sales']['gross_profit'],
            ),
            'expenses_paid' => $this->compare(
                $current['expenses']['paid_amount'],
                $previous['expenses']['paid_amount'],
            ),
            'net_profit' => $this->compare(
                $current['profitLoss']['net_profit'],
                $previous['profitLoss']['net_profit'],
            ),
        ]);
    }

    public function purchases(?array $purchases, bool $includeFinancials): ?array
    {
        if (!$includeFinancials || !$purchases) {
            return null;
        }

        return [
            'total_purchase_amount' => $purchases['total_purchase_amount'],
            'total_return_refund' => $purchases['total_return_refund'],
            'net_purchase_amount' => $purchases['net_purchase_amount'],
            'transaction_count' => $purchases['transaction_count'],
        ];
    }

    public function inventory(array $inventorySummary, bool $includeFinancials): array
    {
        $stockValue = $inventorySummary['stock_value'];
        $lowStock = $inventorySummary['low_stock'];
        $byProductType = collect($inventorySummary['by_product_type'])
            ->map(function (array $item) use ($includeFinancials) {
                if (!$includeFinancials) {
                    unset($item['stock_value']);
                }

                return $item;
            })
            ->values()
            ->toArray();

        $overview = [
            'mobile_devices_available' => $stockValue['mobile_devices']['count'],
            'bulk_quantity_available' => $stockValue['bulk_products']['quantity'],
            'low_stock_count' => $lowStock['low_stock_count'],
            'out_of_stock_count' => $lowStock['out_of_stock_count'],
            'low_stock_products' => collect($lowStock['low_stock_products'])->take(5)->values()->toArray(),
            'out_of_stock_products' => collect($lowStock['out_of_stock_products'])->take(5)->values()->toArray(),
            'by_product_type' => $byProductType,
        ];

        if (!$includeFinancials) {
            return $overview;
        }

        return array_merge($overview, [
            'total_stock_value' => $stockValue['total_stock_value'],
            'mobile_devices_value' => $stockValue['mobile_devices']['value'],
            'bulk_products_value' => $stockValue['bulk_products']['value'],
        ]);
    }

    public function maintenance(array $maintenanceSummary, array $operationsMaintenance, bool $includeFinancials): array
    {
        $overview = [
            'status_counts' => $operationsMaintenance['status_counts'],
            'received_tickets' => $maintenanceSummary['received_tickets'],
            'delivered_tickets' => $maintenanceSummary['delivered_tickets'],
            'repaired_not_delivered' => $operationsMaintenance['repaired_not_delivered'],
            'waiting_parts' => $operationsMaintenance['waiting_parts'],
        ];

        if (!$includeFinancials) {
            return $overview;
        }

        return array_merge($overview, [
            'total_revenue' => $maintenanceSummary['total_revenue'],
            'total_parts_cost' => $maintenanceSummary['total_parts_cost'],
            'gross_profit' => $maintenanceSummary['gross_profit'],
            'total_advance_payment' => $maintenanceSummary['total_advance_payment'],
        ]);
    }

    public function operations(array $operations, bool $includeFinancials): array
    {
        if ($includeFinancials) {
            return $operations;
        }

        unset($operations['expenses']['pending_amount']);
        return $operations;
    }

    private function compare(float|int $current, float|int $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $previous != 0
                ? round((($current - $previous) / $previous) * 100, 2)
                : ($current == 0 ? 0 : 100),
        ];
    }
}
