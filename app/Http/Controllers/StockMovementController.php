<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    //
       public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $stockMovements = StockMovement::with([
            'product',
            'inventoryItem',
            'user',
        ])->latest()->paginate($perPage);

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
