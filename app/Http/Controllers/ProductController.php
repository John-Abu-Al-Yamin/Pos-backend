<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    //


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

        $products = Product::with(['category', 'brand'])
            ->when($request->filled('search'), fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->when($request->filled('category_id'), fn($q) =>
                $q->where('category_id', $request->category_id)
            )
            ->when($request->filled('brand_id'), fn($q) =>
                $q->where('brand_id', $request->brand_id)
            )
            ->when($request->filled('type'), fn($q) =>
                $q->where('type', $request->type)
            )
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب المنتجات بنجاح',
            data: $products
        );
    }


    public function show(int $id)
    {
        $product = Product::with(['category', 'brand'])->find($id);

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
            data: $product,
            message: 'تم تحديث المنتج بنجاح'
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
