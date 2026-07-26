<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\InventoryQuantity;
use Illuminate\Http\Request;

class InventoryQuantityController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $inventory = InventoryQuantity::with('product')
            ->when($request->filled('search'), fn($q) =>
                $q->whereHas('product', fn($q) =>
                    $q->where('name', 'like', '%' . $request->search . '%')))
            ->when($request->filled('type'), fn($q) =>
                $q->whereHas('product', fn($q) =>
                    $q->where('type', $request->type)))
            ->when($request->filled('category_id'), fn($q) =>
                $q->whereHas('product', fn($q) =>
                    $q->where('category_id', $request->category_id)))
            ->when($request->filled('stock_status'), function ($q) use ($request) {
                if ($request->stock_status === 'in') {
                    $q->where('quantity', '>', 0);
                } elseif ($request->stock_status === 'out') {
                    $q->where('quantity', 0);
                } elseif ($request->stock_status === 'low') {
                    $q->join('products', 'inventory_quantities.product_id', '=', 'products.id')
                        ->where('inventory_quantities.quantity', '>', 0)
                        ->whereColumn('inventory_quantities.quantity', '<=', 'products.min_stock')
                        ->select('inventory_quantities.*');
                }
            })
            ->when($request->filled('min_quantity'), fn($q) =>
                $q->where('quantity', '>=', (int) $request->min_quantity))
            ->when($request->filled('max_quantity'), fn($q) =>
                $q->where('quantity', '<=', (int) $request->max_quantity))
            ->when($request->filled('from_date'), fn($q) =>
                $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) =>
                $q->whereDate('created_at', '<=', $request->to_date))
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب بيانات المخزون بنجاح',
            data: $inventory
        );
    }

    public function show(int $id)
    {
        $inventory = InventoryQuantity::with('product')
            ->find($id);

        if (!$inventory) {
            return ApiResponse::error(
                message: 'المخزون غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب بيانات المخزون بنجاح',
            data: $inventory
        );
    }
}
