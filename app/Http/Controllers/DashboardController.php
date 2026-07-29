<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\DashboardRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Dashboard\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    public function index(DashboardRequest $request)
    {
        $data = $this->dashboardService->generate(
            $request->validated(),
            $request->user(),
            $request->resolvedPeriod(),
        );

        return ApiResponse::success(
            message: 'Dashboard data generated successfully',
            data: $data,
        );
    }
}
