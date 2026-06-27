<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\StockItem;
use Illuminate\Http\Request;

class StockItemController extends Controller
{
    public function available(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $productCategory = $request->input('product_category');

        $query = StockItem::available()
            ->with(['product.category']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%"))
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->whereHas('product', fn($p) => $p->where('category_id', $categoryId));
        }

        if ($productCategory) {
            $query->whereHas('product', fn($p) => $p->where('product_category', $productCategory));
        }

        $items = $query->orderBy('product_id')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب المخزون المتاح بنجاح',
            data: $items,
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $productId = $request->input('product_id');
        $productCategory = $request->input('product_category');
        $status = $request->input('status');
        $condition = $request->input('condition');

        $query = StockItem::with(['product.category', 'purchaseItem']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%"))
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->whereHas('product', fn($p) => $p->where('category_id', $categoryId));
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($productCategory) {
            $query->whereHas('product', fn($p) => $p->where('product_category', $productCategory));
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($condition) {
            $query->where('condition', $condition);
        }

        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب عناصر المخزون بنجاح',
            data: $items
        );
    }


    public function show(int $id)
    {
        $item = StockItem::with(['product', 'purchaseItem'])->find($id);

        if (!$item) {
            return ApiResponse::error(
                message: 'عنصر المخزون غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب عنصر المخزون بنجاح',
            data: $item
        );
    }
}
