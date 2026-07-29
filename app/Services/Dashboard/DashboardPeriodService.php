<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Carbon;

class DashboardPeriodService
{
    public function dateRange(array $filters): array
    {
        return [
            Carbon::parse($filters['date_from'])->startOfDay(),
            Carbon::parse($filters['date_to'])->endOfDay(),
        ];
    }

    public function previousPeriod(Carbon $dateFrom, Carbon $dateTo): array
    {
        $days = $dateFrom->diffInDays($dateTo);
        $previousTo = $dateFrom->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days)->startOfDay();

        return [$previousFrom, $previousTo];
    }
}
