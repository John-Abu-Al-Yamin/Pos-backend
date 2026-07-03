<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    //
    public function store(StoreBrandRequest $request)
    {
        $validatedData = $request->validated();

        $brand = Brand::create($validatedData);

        return ApiResponse::success(
            message: 'تم إنشاء العلامة التجارية بنجاح',
            data: $brand
        );
    }

    public function index()
    {
        $brands = Brand::all();

        return ApiResponse::success(
            message: 'تم جلب العلامات التجارية بنجاح',
            data: $brands
        );
    }

    public function show(int $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return ApiResponse::error(
                message: 'العلامة التجارية غير موجودة',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            data: $brand,
            message: 'تم جلب العلامة التجارية بنجاح'
        );
    }

    public function update(UpdateBrandRequest $request, int $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return ApiResponse::error(
                message: 'العلامة التجارية غير موجودة',
                statusCode: 404
            );
        }

        $validatedData = $request->validated();
        $brand->update($validatedData);

        return ApiResponse::success(
            message: 'تم تحديث العلامة التجارية بنجاح',
            data: $brand
        );
    }

    public function destroy(int $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return ApiResponse::error(
                message: 'العلامة التجارية غير موجودة',
                statusCode: 404
            );
        }

        $brand->delete();

        return ApiResponse::success(
            message: 'تم حذف العلامة التجارية بنجاح'
        );
    }
}
