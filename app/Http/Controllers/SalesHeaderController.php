<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\SalesHeader;
use Illuminate\Http\Request;

class SalesHeaderController extends Controller
{
    //

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $sales = SalesHeader::with('customer')->latest()->paginate($perPage);

return ApiResponse::success(
            message: 'تم جلب حركات المخزون بنجاح',
            data: $sales
        );    }

    public function show( int $id)
    {
        $sale = SalesHeader::with([
            'customer',
            'creator',
            'items.product',
            'items.inventoryItem',
        ])->findOrFail($id);

        if (!$sale) {
            return ApiResponse::error(
                message: 'حركة المخزون غير موجودة',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب حركة المخزون بنجاح',
            data: $sale
        );
    }
}