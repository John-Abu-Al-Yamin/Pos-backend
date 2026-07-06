<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\InventoryQuantity;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    //
        public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $inventoryQuantities = InventoryQuantity::with('product')
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب مخزون الكميات بنجاح',
            data: $inventoryQuantities
        );
    }

       public function show(int $id)
    {
        $inventoryQuantity = InventoryQuantity::with('product')->find($id);

        if (!$inventoryQuantity) {
            return ApiResponse::error(
                message: 'المخزون غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب المخزون بنجاح',
            data: $inventoryQuantity
        );
    }

    
}
