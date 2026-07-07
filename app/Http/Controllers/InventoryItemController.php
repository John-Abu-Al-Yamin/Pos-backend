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
