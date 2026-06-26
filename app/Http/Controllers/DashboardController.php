<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\FinancialService;
use App\Services\ProductPerformanceService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FinancialService $financialService,
        private readonly ProductPerformanceService $productPerformanceService,
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

    public function productsPerformance(Request $request)
    {
        $period = $request->input('period', 'month');
        $from = $request->input('from');
        $to = $request->input('to');
        $limit = (int) $request->input('limit', 10);

        if ($period !== 'custom') {
            $dates = $this->resolvePeriod($period);
            $from = $dates['from'];
            $to = $dates['to'];
        }

        $performance = $this->productPerformanceService->getPerformance($from, $to, $limit);

        return ApiResponse::success(
            message: 'تم جلب أداء المنتجات بنجاح',
            data: $performance
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
