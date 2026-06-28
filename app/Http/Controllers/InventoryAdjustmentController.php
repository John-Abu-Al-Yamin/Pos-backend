<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryAdjustment\StoreInventoryAdjustmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryAdjustment;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        private readonly InventoryAdjustmentService $adjustmentService,
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');

        $query = InventoryAdjustment::with(['product', 'createdBy']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('employee_id')) {
            $query->where('created_by', $request->employee_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $adjustments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب تسويات المخزون بنجاح',
            data: $adjustments,
        );
    }

    public function store(StoreInventoryAdjustmentRequest $request)
    {
        $data = $request->validated();
        $userId = auth()->id();

        try {
            $adjustment = $this->adjustmentService->createAdjustment($data, $userId);

            return ApiResponse::success(
                message: 'تم إنشاء تسوية المخزون بنجاح',
                data: $adjustment,
                statusCode: 201,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 422,
            );
        }
    }

    public function show(int $id)
    {
        $adjustment = InventoryAdjustment::with(['product', 'createdBy'])->find($id);

        if (!$adjustment) {
            return ApiResponse::error(
                message: 'تسوية المخزون غير موجودة',
                statusCode: 404,
            );
        }

        return ApiResponse::success(
            message: 'تم جلب تسوية المخزون بنجاح',
            data: $adjustment,
        );
    }

    public function destroy(int $id)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'admin') {
            return ApiResponse::error(
                message: 'ليس لديك صلاحية حذف تسويات المخزون',
                statusCode: 403,
            );
        }

        $adjustment = InventoryAdjustment::find($id);

        if (!$adjustment) {
            return ApiResponse::error(
                message: 'تسوية المخزون غير موجودة',
                statusCode: 404,
            );
        }

        $adjustment->delete();

        return ApiResponse::success(
            message: 'تم حذف تسوية المخزون بنجاح',
        );
    }

    public function summary()
    {
        $today = now()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $monthEnd = now()->endOfMonth()->format('Y-m-d');

        $todayCount = InventoryAdjustment::whereDate('created_at', $today)->count();

        $todayIncreased = InventoryAdjustment::whereDate('created_at', $today)
            ->where('difference', '>', 0)
            ->sum('difference');

        $todayDecreased = InventoryAdjustment::whereDate('created_at', $today)
            ->where('difference', '<', 0)
            ->sum(DB::raw('ABS(difference)'));

        $monthCount = InventoryAdjustment::whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $monthEnd)
            ->count();

        $monthIncreased = InventoryAdjustment::whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $monthEnd)
            ->where('difference', '>', 0)
            ->sum('difference');

        $monthDecreased = InventoryAdjustment::whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $monthEnd)
            ->where('difference', '<', 0)
            ->sum(DB::raw('ABS(difference)'));

        $damagedQuantity = InventoryAdjustment::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('reason', ['Damaged', 'Broken'])
            ->sum(DB::raw('ABS(difference)'));

        $lostQuantity = InventoryAdjustment::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('reason', 'Lost')
            ->sum(DB::raw('ABS(difference)'));

        $stolenQuantity = InventoryAdjustment::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('reason', 'Stolen')
            ->sum(DB::raw('ABS(difference)'));

        return ApiResponse::success(
            message: 'تم جلب ملخص تسويات المخزون بنجاح',
            data: [
                'today_count' => (int) $todayCount,
                'today_increased' => (int) $todayIncreased,
                'today_decreased' => (int) $todayDecreased,
                'month_count' => (int) $monthCount,
                'month_increased' => (int) $monthIncreased,
                'month_decreased' => (int) $monthDecreased,
                'damaged_quantity' => (int) $damagedQuantity,
                'lost_quantity' => (int) $lostQuantity,
                'stolen_quantity' => (int) $stolenQuantity,
            ],
        );
    }
}
