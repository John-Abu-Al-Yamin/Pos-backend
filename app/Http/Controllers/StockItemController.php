<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\StockItem;
use Illuminate\Http\Request;

class StockItemController extends Controller
{

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $items = StockItem::with(['product', 'purchaseItem'])->paginate($perPage);

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
