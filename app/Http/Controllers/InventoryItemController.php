<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $items = InventoryItem::with('product')
            ->when($request->filled('search'), fn($q) =>
                $q->where(function ($q) use ($request) {
                    $q->where('internal_serial', 'like', '%' . $request->search . '%')
                        ->orWhereHas('product', fn($q) =>
                            $q->where('name', 'like', '%' . $request->search . '%'));
                }))
            ->when($request->filled('type'), fn($q) =>
                $q->whereHas('product', fn($q) =>
                    $q->where('type', $request->type)))
            ->when($request->filled('category_id'), fn($q) =>
                $q->whereHas('product', fn($q) =>
                    $q->where('category_id', $request->category_id)))
            ->when($request->filled('status'), fn($q) =>
                $q->where('status', $request->status))
            ->when($request->filled('source'), fn($q) =>
                $q->where('source', $request->source))
            ->when($request->filled('from_date'), fn($q) =>
                $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) =>
                $q->whereDate('created_at', '<=', $request->to_date))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب عناصر المخزون بنجاح',
            data: $items
        );
    }

    public function show(int $id)
    {
        $item = InventoryItem::with('product')->find($id);

        if (!$item) {
            return ApiResponse::error(
                message: 'العنصر غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب العنصر بنجاح',
            data: $item
        );
    }
}
