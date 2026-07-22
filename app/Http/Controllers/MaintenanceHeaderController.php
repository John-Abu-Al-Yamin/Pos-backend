<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceHeader\StoreMaintenanceHeaderRequest;
use App\Http\Requests\MaintenanceHeader\UpdateMaintenanceHeaderRequest;
use App\Http\Requests\MaintenanceHeader\UpdateMaintenanceStatusRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenanceHeader;
use App\Services\Maintenance\MaintenanceStatusService;
use Illuminate\Http\Request;

class MaintenanceHeaderController extends Controller
{
    public function __construct(
        private MaintenanceStatusService $maintenanceStatusService
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);

        $query = MaintenanceHeader::with([
            'customer',
            'maintenanceDevice.product',
            'createdBy',
        ])->withCount(['operations', 'usedParts'])
          ->withSum('operations', 'cost')
          ->withSum('usedParts', 'total_price');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('maintenanceDevice', function ($dq) use ($search) {
                      $dq->where('serial_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('received_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('received_date', '<=', $request->input('to_date'));
        }

        $headers = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب تذاكر الصيانة بنجاح',
            data: $headers
        );
    }

    public function show(int $id)
    {
        $header = MaintenanceHeader::with([
            'customer',
            'maintenanceDevice.product',
            'createdBy',
            'operations',
            'usedParts.product',
        ])->withSum('operations', 'cost')
          ->withSum('usedParts', 'total_price')
          ->find($id);

        if (!$header) {
            return ApiResponse::error(
                message: 'تذكرة الصيانة غير موجودة',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب تذكرة الصيانة بنجاح',
            data: $header
        );
    }

    public function update(UpdateMaintenanceHeaderRequest $request, int $id)
    {
        $header = MaintenanceHeader::find($id);

        if (!$header) {
            return ApiResponse::error(
                message: 'تذكرة الصيانة غير موجودة',
                statusCode: 404
            );
        }

        if ($header->isTerminal()) {
            return ApiResponse::error(
                message: 'لا يمكن تعديل تذكرة صيانة تم تسليمها أو إلغاؤها.',
                statusCode: 400
            );
        }

        $header->update($request->validated());

        $header->refresh();
        $header->loadSum('operations', 'cost');
        $header->loadSum('usedParts', 'total_price');

        return ApiResponse::success(
            message: 'تم تحديث تذكرة الصيانة بنجاح',
            data: $header
        );
    }

    public function updateStatus(UpdateMaintenanceStatusRequest $request, int $id)
    {
        $header = MaintenanceHeader::find($id);

        if (!$header) {
            return ApiResponse::error(
                message: 'تذكرة الصيانة غير موجودة',
                statusCode: 404
            );
        }

        try {
            $header = $this->maintenanceStatusService->transition(
                $header,
                $request->input('status'),
                $request->input('delivery_date'),
                $request->input('paid_amount')
            );

            $header->loadSum('operations', 'cost');
            $header->loadSum('usedParts', 'total_price');
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم تحديث حالة تذكرة الصيانة بنجاح',
            data: $header
        );
    }

    public function destroy(int $id)
    {
        $header = MaintenanceHeader::find($id);

        if (!$header) {
            return ApiResponse::error(
                message: 'تذكرة الصيانة غير موجودة',
                statusCode: 404
            );
        }

        if (!$header->isPending()) {
            return ApiResponse::error(
                message: 'لا يمكن حذف تذكرة صيانة إلا في حالة قيد الانتظار.',
                statusCode: 400
            );
        }

        $header->delete();

        return ApiResponse::success(
            message: 'تم حذف تذكرة الصيانة بنجاح'
        );
    }
}
