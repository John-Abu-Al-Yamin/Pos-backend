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

        $query = SalesHeader::with('customer', 'createdBy');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('createdBy', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->input('created_by'));
        }

        $sales = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب حركات المخزون بنجاح',
            data: $sales
        );
    }

    public function show( int $id)
    {
        $sale = SalesHeader::with([
            'customer',
            'createdBy',
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