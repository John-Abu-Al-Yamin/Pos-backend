<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\PurchaseReturnHeader;
use Illuminate\Http\Request;

class PurchaseReturnHeaderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);

        $query = PurchaseReturnHeader::with([
            'purchaseHeader',
            'supplier',
            'user',
        ])->withCount('items');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseHeader', function ($pq) use ($search) {
                        $pq->where('purchaseHeader_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('purchase_header_id')) {
            $query->where('purchase_header_id', $request->input('purchase_header_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('return_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('return_date', '<=', $request->input('to_date'));
        }

        $returns = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'Purchase returns retrieved successfully',
            data: $returns
        );
    }

    public function show(int $id)
    {
        $return = PurchaseReturnHeader::with([
            'purchaseHeader',
            'supplier',
            'user',
            'items.purchaseItem',
            'items.product',
            'items.inventoryItem',
        ])->find($id);

        if (! $return) {
            return ApiResponse::error(
                message: 'Purchase return not found',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'Purchase return retrieved successfully',
            data: $return
        );
    }
}
