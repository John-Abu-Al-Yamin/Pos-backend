<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceOperation\StoreMaintenanceOperationRequest;
use App\Http\Requests\MaintenanceOperation\UpdateMaintenanceOperationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenanceHeader;
use App\Models\MaintenanceOperation;
use App\Services\Maintenance\MaintenanceOperationService;
use DomainException;

class MaintenanceOperationController extends Controller
{
    public function __construct(
        private MaintenanceOperationService $maintenanceOperationService
    ) {}

    public function index(MaintenanceHeader $header)
    {
        $operations = $header->operations()->latest()->get();

        return ApiResponse::success(
            message: 'تم جلب عمليات الصيانة بنجاح',
            data: $operations
        );
    }

    public function store(StoreMaintenanceOperationRequest $request, MaintenanceHeader $header)
    {
        try {
            $operation = $this->maintenanceOperationService->addOperation(
                $header,
                $request->validated()
            );
        } catch (DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم إضافة عملية الصيانة بنجاح',
            data: $operation,
            statusCode: 201
        );
    }

    public function show(MaintenanceHeader $header, MaintenanceOperation $operation)
    {
        if ($operation->maintenance_header_id !== $header->id) {
            return ApiResponse::error(
                message: 'عملية الصيانة غير تابعة لهذه التذكرة.',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب عملية الصيانة بنجاح',
            data: $operation
        );
    }

    public function update(
        UpdateMaintenanceOperationRequest $request,
        MaintenanceHeader $header,
        MaintenanceOperation $operation
    ) {
        try {
            $operation = $this->maintenanceOperationService->updateOperation(
                $header,
                $operation,
                $request->validated()
            );
        } catch (DomainException $e) {
            if ($e->getMessage() === 'عملية الصيانة غير تابعة لهذه التذكرة.') {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    statusCode: 404
                );
            }
            
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم تحديث عملية الصيانة بنجاح',
            data: $operation
        );
    }

    public function destroy(MaintenanceHeader $header, MaintenanceOperation $operation)
    {
        try {
            $this->maintenanceOperationService->removeOperation($header, $operation);
        } catch (DomainException $e) {
            if ($e->getMessage() === 'عملية الصيانة غير تابعة لهذه التذكرة.') {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    statusCode: 404
                );
            }

            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم حذف عملية الصيانة بنجاح'
        );
    }
}
