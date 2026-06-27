<?php

namespace App\Http\Controllers;

use App\Http\Requests\Repair\StoreRepairRequest;
use App\Http\Requests\Repair\UpdateRepairRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Repair;
use App\Models\StockItem;
use App\Models\User;
use App\Services\RepairService;
use Illuminate\Http\Request;

class RepairController extends Controller
{
    public function __construct(
        private readonly RepairService $repairService,
    ) {}

    public function store(StoreRepairRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $data['user_id'] = $user->id;

        $repair = $this->repairService->createRepair($data);

        return ApiResponse::success(
            message: 'تم إنشاء أمر الإصلاح بنجاح',
            data: $repair,
            statusCode: 201,
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Repair::with(['customer', 'repairParts.product', 'user']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('device_type', 'like', "%{$search}%")
                  ->orWhere('device_serial', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $repairs = $query->orderBy('id', 'desc')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب أوامر الإصلاح بنجاح',
            data: $repairs,
        );
    }

    public function show(int $id)
    {
        $repair = Repair::with(['customer', 'repairParts.product', 'repairParts.stockItem', 'user'])->find($id);

        if (!$repair) {
            return ApiResponse::error(
                message: 'أمر الإصلاح غير موجود',
                statusCode: 404,
            );
        }

        return ApiResponse::success(
            message: 'تم جلب أمر الإصلاح بنجاح',
            data: $repair,
        );
    }

    public function update(UpdateRepairRequest $request, int $id)
    {
        $repair = Repair::find($id);

        if (!$repair) {
            return ApiResponse::error(
                message: 'أمر الإصلاح غير موجود',
                statusCode: 404,
            );
        }

        $data = $request->validated();
        $repair = $this->repairService->updateRepair($repair, $data);

        return ApiResponse::success(
            message: 'تم تحديث أمر الإصلاح بنجاح',
            data: $repair,
        );
    }

    public function complete(Request $request, int $id)
    {
        $repair = Repair::find($id);

        if (!$repair) {
            return ApiResponse::error(
                message: 'أمر الإصلاح غير موجود',
                statusCode: 404,
            );
        }

        if ($repair->status === 'completed') {
            return ApiResponse::error(
                message: 'أمر الإصلاح مكتمل بالفعل',
                statusCode: 422,
            );
        }

        $markAsPaid = (bool) $request->input('mark_as_paid', false);

        $repair = $this->repairService->completeRepair($repair, $markAsPaid);

        return ApiResponse::success(
            message: $markAsPaid ? 'تم إكمال أمر الإصلاح وتسجيل الدفع بنجاح' : 'تم إكمال أمر الإصلاح بنجاح',
            data: $repair,
        );
    }

    public function pay(int $id)
    {
        $repair = Repair::find($id);

        if (!$repair) {
            return ApiResponse::error(
                message: 'أمر الإصلاح غير موجود',
                statusCode: 404,
            );
        }

        if ($repair->payment_status === 'paid') {
            return ApiResponse::error(
                message: 'تم دفع أمر الإصلاح بالفعل',
                statusCode: 422,
            );
        }

        $repair = $this->repairService->payRepair($repair);

        return ApiResponse::success(
            message: 'تم تسجيل الدفع بنجاح',
            data: $repair,
        );
    }

    public function cancel(int $id)
    {
        $repair = Repair::find($id);

        if (!$repair) {
            return ApiResponse::error(
                message: 'أمر الإصلاح غير موجود',
                statusCode: 404,
            );
        }

        if (in_array($repair->status, ['completed', 'cancelled'])) {
            return ApiResponse::error(
                message: 'لا يمكن إلغاء أمر الإصلاح في هذه الحالة',
                statusCode: 422,
            );
        }

        $repair = $this->repairService->cancelRepair($repair);

        return ApiResponse::success(
            message: 'تم إلغاء أمر الإصلاح بنجاح',
            data: $repair,
        );
    }

    public function destroy(int $id)
    {
        $repair = Repair::find($id);

        if (!$repair) {
            return ApiResponse::error(
                message: 'أمر الإصلاح غير موجود',
                statusCode: 404,
            );
        }

        if ($repair->repairParts()->exists()) {
            $stockItemIds = $repair->repairParts()->pluck('stock_item_id');
            StockItem::whereIn('id', $stockItemIds)
                ->where('status', 'consumed')
                ->update(['status' => 'available']);
        }

        $repair->delete();

        return ApiResponse::success(
            message: 'تم حذف أمر الإصلاح بنجاح',
        );
    }
}
