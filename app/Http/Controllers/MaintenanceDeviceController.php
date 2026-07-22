<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceDevice\StoreMaintenanceDeviceRequest;
use App\Http\Requests\MaintenanceDevice\UpdateMaintenanceDeviceRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenanceDevice;
use Illuminate\Http\Request;

class MaintenanceDeviceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $query = MaintenanceDevice::with('product');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $devices = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب الأجهزة بنجاح',
            data: $devices
        );
    }

    public function store(StoreMaintenanceDeviceRequest $request)
    {
        $device = MaintenanceDevice::create($request->validated());

        return ApiResponse::success(
            message: 'تم إنشاء الجهاز بنجاح',
            data: $device,
            statusCode: 201
        );
    }

    public function show(int $id)
    {
        $device = MaintenanceDevice::with('product', 'maintenanceHeaders')->find($id);

        if (!$device) {
            return ApiResponse::error(
                message: 'الجهاز غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب الجهاز بنجاح',
            data: $device
        );
    }

    public function update(UpdateMaintenanceDeviceRequest $request, int $id)
    {
        $device = MaintenanceDevice::find($id);

        if (!$device) {
            return ApiResponse::error(
                message: 'الجهاز غير موجود',
                statusCode: 404
            );
        }

        $device->update($request->validated());

        return ApiResponse::success(
            message: 'تم تحديث الجهاز بنجاح',
            data: $device
        );
    }

    public function destroy(int $id)
    {
        $device = MaintenanceDevice::find($id);

        if (!$device) {
            return ApiResponse::error(
                message: 'الجهاز غير موجود',
                statusCode: 404
            );
        }

        $device->delete();

        return ApiResponse::success(
            message: 'تم حذف الجهاز بنجاح'
        );
    }
}
