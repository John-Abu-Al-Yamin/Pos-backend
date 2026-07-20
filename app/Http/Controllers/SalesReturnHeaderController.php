<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\SalesReturnHeader;
use Illuminate\Http\Request;

class SalesReturnHeaderController extends Controller
{

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);

        $query = SalesReturnHeader::with([
            'salesHeader',
            'customer',
            'user',
        ])->withCount('items');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('salesHeader', function ($sq) use ($search) {
                        $sq->where('invoice_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('sales_header_id')) {
            $query->where('sales_header_id', $request->input('sales_header_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('return_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('return_date', '<=', $request->input('to_date'));
        }

        $returns = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'Sales returns retrieved successfully',
            data: $returns
        );
    }

    public function show(int $id)
    {
        $return = SalesReturnHeader::with([
            'salesHeader',
            'customer',
            'user',
            'items.salesItem',
            'items.product',
            'items.inventoryItem',
        ])->find($id);

        if (! $return) {
            return ApiResponse::error(
                message: 'Sales return not found',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'Sales return retrieved successfully',
            data: $return
        );
    }
}
