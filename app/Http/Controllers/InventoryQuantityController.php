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
