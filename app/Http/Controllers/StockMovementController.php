<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $stockMovements = StockMovement::with([
            'product',
            'inventoryItem',
            'user',
        ])
            ->when($request->filled('search'), fn($q) =>
                $q->where(function ($q) use ($request) {
                    $q->whereHas('product', fn($q) =>
                        $q->where('name', 'like', '%' . $request->search . '%'))
                        ->orWhereHas('inventoryItem', fn($q) =>
                            $q->where('internal_serial', 'like', '%' . $request->search . '%'))
                        ->orWhere('notes', 'like', '%' . $request->search . '%');
                }))
            ->when($request->filled('movement_type'), fn($q) =>
                $q->where('movement_type', $request->movement_type))
            ->when($request->filled('movement'), fn($q) =>
                $q->where('movement', $request->movement))
            ->when($request->filled('reference_type'), fn($q) =>
                $q->where('reference_type', 'like', '%' . $request->reference_type . '%'))
            ->when($request->filled('product_type'), fn($q) =>
                $q->whereHas('product', fn($q) =>
                    $q->where('type', $request->product_type)))
            ->when($request->filled('category_id'), fn($q) =>
                $q->whereHas('product', fn($q) =>
                    $q->where('category_id', $request->category_id)))
            ->when($request->filled('created_by'), fn($q) =>
                $q->where('created_by', $request->created_by))
            ->when($request->filled('from_date'), fn($q) =>
                $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) =>
                $q->whereDate('created_at', '<=', $request->to_date))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب حركات المخزون بنجاح',
            data: $stockMovements
        );
    }

    public function show(int $id)
    {
        $stockMovement = StockMovement::with([
            'product',
            'inventoryItem',
            'user',
        ])->find($id);

        if (!$stockMovement) {
            return ApiResponse::error(
                message: 'حركة المخزون غير موجودة',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب حركة المخزون بنجاح',
            data: $stockMovement
        );
    }
}
