<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $product = Product::create($data);
        return ApiResponse::success(
            message: 'تم إنشاء المنتج بنجاح',
            data: $product

        );
    }
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $isSerialized = $request->input('is_serialized');

        $query = Product::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($isSerialized !== null && $isSerialized !== '') {
            $query->where('is_serialized', filter_var($isSerialized, FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم استرجاع المنتجات بنجاح',
            data: $products
        );
    }

    public function show(int $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return ApiResponse::error(
                message: 'المنتج غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            data: $product,
            message: 'تم جلب المنتج بنجاح'
        );
    }

    public function update(UpdateProductRequest $request, int $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return ApiResponse::error(
                message: 'المنتج غير موجود',
                statusCode: 404
            );
        }

        $data = $request->validated();
        $product->update($data);

        return ApiResponse::success(
            message: 'تم تحديث المنتج بنجاح',
            data: $product
        );
    }

    public function destroy(int $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return ApiResponse::error(
                message: 'المنتج غير موجود',
                statusCode: 404
            );
        }

        $product->delete();

        return ApiResponse::success(
            message: 'تم حذف المنتج بنجاح'
        );
    }
}
