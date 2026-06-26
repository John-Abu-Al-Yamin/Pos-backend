<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\FinancialService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FinancialService $financialService,
    ) {}

    public function financial(Request $request)
    {
        $period = $request->input('period', 'month');
        $from = $request->input('from');
        $to = $request->input('to');

        if ($period !== 'custom') {
            $dates = $this->resolvePeriod($period);
            $from = $dates['from'];
            $to = $dates['to'];
        }

        $metrics = $this->financialService->getMetrics($from, $to);

        return ApiResponse::success(
            message: 'تم جلب البيانات المالية بنجاح',
            data: $metrics
        );
    }

    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'today' => [
                'from' => now()->format('Y-m-d'),
                'to' => now()->format('Y-m-d'),
            ],
            'week' => [
                'from' => now()->startOfWeek()->format('Y-m-d'),
                'to' => now()->endOfWeek()->format('Y-m-d'),
            ],
            'year' => [
                'from' => now()->startOfYear()->format('Y-m-d'),
                'to' => now()->endOfYear()->format('Y-m-d'),
            ],
            default => [ // 'month'
                'from' => now()->startOfMonth()->format('Y-m-d'),
                'to' => now()->endOfMonth()->format('Y-m-d'),
            ],
        };
    }
}
