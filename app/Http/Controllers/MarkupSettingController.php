<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkupSetting\StoreMarkupSettingRequest;
use App\Http\Requests\MarkupSetting\UpdateMarkupSettingRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarkupSetting;

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

        if (!$setting) {
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

        if (!$setting) {
            return ApiResponse::error(
                message: 'إعداد الربح غير موجود',
                statusCode: 404
            );
        }

        $data = $request->validated();
        $setting->update($data);

        return ApiResponse::success(
            data: $setting,
            message: 'تم تحديث إعداد الربح بنجاح'
        );
    }

    public function destroy(int $id)
    {
        $setting = MarkupSetting::find($id);

        if (!$setting) {
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
