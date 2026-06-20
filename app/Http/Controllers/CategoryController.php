<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $category = Category::create($data);
        return ApiResponse::success(
            message: 'تم إنشاء التصنيف بنجاح',
            data: $category
        );
    }

    public function index()
    {
        $categories = Category::all();
        return ApiResponse::success(
            message: 'تم جلب التصنيفات بنجاح',
            data: $categories
        );
    }

    public function show(int $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return ApiResponse::error(
                message: 'التصنيف غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            data: $category,
            message: 'تم جلب التصنيف بنجاح'
        );
    }

    public function update(UpdateCategoryRequest $request, int $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return ApiResponse::error(
                message: 'التصنيف غير موجود',
                statusCode: 404
            );
        }

        $data = $request->validated();
        $category->update($data);

        return ApiResponse::success(
            data: $category,
            message: 'تم تحديث التصنيف بنجاح'
        );
    }

    public function destroy(int $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return ApiResponse::error(
                message: 'التصنيف غير موجود',
                statusCode: 404
            );
        }

        $category->delete();

        return ApiResponse::success(
            message: 'تم حذف التصنيف بنجاح'
        );
    }
}
