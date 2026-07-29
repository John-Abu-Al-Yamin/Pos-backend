<?php

namespace App\Services\Dashboard;

class DashboardAlertService
{
    public function alerts(array $inventory, array $operations, bool $includeFinancials): array
    {
        $alerts = [
            'low_stock' => [
                'count' => $inventory['low_stock_count'],
            ],
            'out_of_stock' => [
                'count' => $inventory['out_of_stock_count'],
            ],
            'maintenance_waiting_parts' => [
                'count' => $operations['maintenance']['waiting_parts'],
            ],
            'pending_expenses' => [
                'count' => $operations['expenses']['pending_count'],
            ],
            'draft_purchases' => [
                'count' => $operations['purchases']['draft_count'],
            ],
        ];

        if ($includeFinancials) {
            $alerts['pending_expenses']['amount'] = $operations['expenses']['pending_amount'];
        }

        return $alerts;
    }
}
