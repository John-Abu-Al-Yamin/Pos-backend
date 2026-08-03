<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkupSetting\StoreMarkupSettingRequest;
use App\Http\Requests\MarkupSetting\UpdateMarkupSettingRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarkupSetting;
use App\Services\Audit\AuditLogService;

class MarkupSettingController extends Controller
{
    public function index()
    {
        $settings = MarkupSetting::all();

        return ApiResponse::success(
            message: 'تم جلب إعدادات الربح بنجاح',
            data: $settings
        );
    }

    public function store(StoreMarkupSettingRequest $request)
    {
        $data = $request->validated();
        $setting = MarkupSetting::create($data);

        return ApiResponse::success(
            message: 'تم إنشاء إعداد الربح بنجاح',
            data: $setting,
            statusCode: 201
        );
    }

    public function show(int $id)
    {
        $setting = MarkupSetting::find($id);

        if (! $setting) {
            return ApiResponse::error(
                message: 'إعداد الربح غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            data: $setting,
            message: 'تم جلب إعداد الربح بنجاح'
        );
    }

    public function update(UpdateMarkupSettingRequest $request, int $id)
    {
        $setting = MarkupSetting::find($id);

        if (! $setting) {
            return ApiResponse::error(
                message: 'إعداد الربح غير موجود',
                statusCode: 404
            );
        }

        $data = $request->validated();
        $oldPercentage = $setting->profit_percentage;

        AuditLogService::withoutModelEvents(fn () => $setting->update($data));

        app(AuditLogService::class)->record(
            module: 'products',
            action: 'product_price_changed',
            auditable: $setting->fresh(),
            oldValues: ['profit_percentage' => $oldPercentage],
            newValues: ['profit_percentage' => $setting->fresh()->profit_percentage],
            changedFields: ['profit_percentage'],
            metadata: [
                'product_type' => $setting->fresh()->product_type,
                'pricing_rule_id' => $setting->id,
            ],
            severity: 'warning',
        );

        return ApiResponse::success(
            data: $setting,
            message: 'تم تحديث إعداد الربح بنجاح'
        );
    }

    public function destroy(int $id)
    {
        $setting = MarkupSetting::find($id);

        if (! $setting) {
            return ApiResponse::error(
                message: 'إعداد الربح غير موجود',
                statusCode: 404
            );
        }

        $setting->delete();

        return ApiResponse::success(
            message: 'تم حذف إعداد الربح بنجاح'
        );
    }
}
