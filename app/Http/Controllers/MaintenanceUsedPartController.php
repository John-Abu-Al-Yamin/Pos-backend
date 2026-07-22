<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceUsedPart\StoreMaintenanceUsedPartRequest;
use App\Http\Requests\MaintenanceUsedPart\UpdateMaintenanceUsedPartRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenanceHeader;
use App\Models\MaintenanceUsedPart;
use App\Services\Maintenance\MaintenancePartService;

class MaintenanceUsedPartController extends Controller
{
    public function __construct(
        private MaintenancePartService $maintenancePartService
    ) {}

    public function index(MaintenanceHeader $header)
    {
        $parts = $header->usedParts()->with('product')->latest()->get();

        return ApiResponse::success(
            message: 'تم جلب قطع الغيار المستخدمة بنجاح',
            data: $parts
        );
    }

    public function store(StoreMaintenanceUsedPartRequest $request, MaintenanceHeader $header)
    {
        try {
            $part = $this->maintenancePartService->addPart(
                $header,
                $request->validated()
            );
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم إضافة قطعة الغيار بنجاح',
            data: $part,
            statusCode: 201
        );
    }

    public function show(MaintenanceHeader $header, MaintenanceUsedPart $part)
    {
        if ($part->maintenance_header_id !== $header->id) {
            return ApiResponse::error(
                message: 'قطعة الغيار غير تابعة لهذه التذكرة.',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب قطعة الغيار بنجاح',
            data: $part->load('product')
        );
    }

    public function update(
        UpdateMaintenanceUsedPartRequest $request,
        MaintenanceHeader $header,
        MaintenanceUsedPart $part
    ) {
        if ($part->maintenance_header_id !== $header->id) {
            return ApiResponse::error(
                message: 'قطعة الغيار غير تابعة لهذه التذكرة.',
                statusCode: 404
            );
        }

        try {
            $part = $this->maintenancePartService->updatePart(
                $header,
                $part,
                $request->validated()
            );
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم تحديث قطعة الغيار بنجاح',
            data: $part
        );
    }

    public function destroy(MaintenanceHeader $header, MaintenanceUsedPart $part)
    {
        if ($part->maintenance_header_id !== $header->id) {
            return ApiResponse::error(
                message: 'قطعة الغيار غير تابعة لهذه التذكرة.',
                statusCode: 404
            );
        }

        try {
            $this->maintenancePartService->removePart($header, $part);
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم حذف قطعة الغيار بنجاح'
        );
    }
}
