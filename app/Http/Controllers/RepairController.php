<?php

namespace App\Http\Controllers;

use App\Http\Requests\Repair\StoreRepairRequest;
use App\Http\Requests\Repair\UpdateRepairRequest;
use App\Http\Requests\Repair\VoidRepairRequest;
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

        if ($repair->status === 'completed') {
            return ApiResponse::error(
                message: 'لا يمكن تعديل أمر الإصلاح بعد اكتماله',
                statusCode: 422,
            );
        }

        $data = $request->validated();

        try {
            $repair = $this->repairService->updateRepair($repair, $data);

            return ApiResponse::success(
                message: 'تم تحديث أمر الإصلاح بنجاح',
                data: $repair,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 422,
            );
        }
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

        if ($repair->voided_at) {
            return ApiResponse::error(
                message: 'لا يمكن إكمال أمر إصلاح ملغي',
                statusCode: 422,
            );
        }

        if ($repair->status === 'completed') {
            return ApiResponse::error(
                message: 'أمر الإصلاح مكتمل بالفعل',
                statusCode: 422,
            );
        }

        $markAsPaid = (bool) $request->input('mark_as_paid', false);

        try {
            $repair = $this->repairService->completeRepair($repair, $markAsPaid);

            return ApiResponse::success(
                message: $markAsPaid ? 'تم إكمال أمر الإصلاح وتسجيل الدفع بنجاح' : 'تم إكمال أمر الإصلاح بنجاح',
                data: $repair,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 422,
            );
        }
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

        if ($repair->voided_at) {
            return ApiResponse::error(
                message: 'لا يمكن دفع أمر إصلاح ملغي',
                statusCode: 422,
            );
        }

        if ($repair->payment_status === 'paid') {
            return ApiResponse::error(
                message: 'تم دفع أمر الإصلاح بالفعل',
                statusCode: 422,
            );
        }

        try {
            $repair = $this->repairService->payRepair($repair);

            return ApiResponse::success(
                message: 'تم تسجيل الدفع بنجاح',
                data: $repair,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 422,
            );
        }
    }

    public function void(VoidRepairRequest $request, int $id)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return ApiResponse::error(
                message: 'ليس لديك صلاحية إلغاء أوامر الإصلاح',
                statusCode: 403,
            );
        }

        $repair = Repair::whereNull('voided_at')->find($id);

        if (!$repair) {
            return ApiResponse::error(
                message: 'أمر الإصلاح غير موجود أو ملغي بالفعل',
                statusCode: 404,
            );
        }

        $data = $request->validated();

        try {
            $repair = $this->repairService->voidRepair($repair, $user->id, $data['void_reason']);

            return ApiResponse::success(
                message: 'تم إلغاء أمر الإصلاح بنجاح',
                data: $repair,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 422,
            );
        }
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

        if ($repair->voided_at) {
            return ApiResponse::error(
                message: 'لا يمكن إلغاء أمر إصلاح ملغي',
                statusCode: 422,
            );
        }

        if (in_array($repair->status, ['completed', 'cancelled'])) {
            return ApiResponse::error(
                message: 'لا يمكن إلغاء أمر الإصلاح في هذه الحالة',
                statusCode: 422,
            );
        }

        try {
            $repair = $this->repairService->cancelRepair($repair);

            return ApiResponse::success(
                message: 'تم إلغاء أمر الإصلاح بنجاح',
                data: $repair,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 422,
            );
        }
    }
}
