<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseHeader\StorePurchaseHeaderRequest;
use App\Http\Requests\PurchaseHeader\UpdatePurchaseHeaderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\PurchaseHeader;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Http\Request;

class PurchaseHeaderController extends Controller
{
    //
    public function store(StorePurchaseHeaderRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $data['created_by_name'] = $user->role === 'admin'
            ? User::where('role', 'admin')->value('name')
            : $user->name;
        $purchaseHeader = PurchaseHeader::create($data);
        $purchaseHeader->load(['supplier', 'purchaseItems.product', 'purchaseItems.stockItems']);

        return ApiResponse::success(
            message: 'تمإنشاء الشراء بنجاح',
            data: $purchaseHeader
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $type = $request->input('type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = PurchaseHeader::with(['supplier', 'purchaseItems.product', 'purchaseItems.stockItems']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $purchaseHeaders = $query->orderBy('id', 'desc')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب الشراء بنجاح',
            data: $purchaseHeaders
        );
    }

    public function show(int $id)
    {
        $purchaseHeader = PurchaseHeader::with(['supplier', 'purchaseItems.product', 'purchaseItems.stockItems'])->find($id);

        if (!$purchaseHeader) {
            return ApiResponse::error(
                message: 'الشراء غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            message: 'تم جلب الشراء بنجاح',
            data: $purchaseHeader
        );
    }

    public function update(UpdatePurchaseHeaderRequest $request, int $id)
    {
        $purchaseHeader = PurchaseHeader::find($id);

        if (!$purchaseHeader) {
            return ApiResponse::error(
                message: 'الشراء غير موجود',
                statusCode: 404
            );
        }

        $data = $request->validated();
        $purchaseHeader->update($data);
        $purchaseHeader->load(['supplier', 'purchaseItems.product', 'purchaseItems.stockItems']);

        return ApiResponse::success(
            data: $purchaseHeader,
            message: 'تم تحديث الشراء بنجاح'
        );
    }

    public function destroy(int $id)
    {
        $purchaseHeader = PurchaseHeader::with('purchaseItems')->find($id);

        if (!$purchaseHeader) {
            return ApiResponse::error(
                message: 'الشراء غير موجود',
                statusCode: 404
            );
        }

        $purchaseItemIds = $purchaseHeader->purchaseItems->pluck('id');

        if ($purchaseItemIds->isNotEmpty()) {
            $hasNonAvailableStock = StockItem::whereIn('purchase_item_id', $purchaseItemIds)
                ->where('status', '!=', 'available')
                ->exists();

            if ($hasNonAvailableStock) {
                return ApiResponse::error(
                    message: 'لا يمكن حذف هذا الشراء لأن بعض عناصر المخزون تم بيعها بالفعل.',
                    statusCode: 422
                );
            }

            StockItem::whereIn('purchase_item_id', $purchaseItemIds)
                ->where('status', 'available')
                ->delete();
        }

        $purchaseHeader->delete();

        return ApiResponse::success(
            message: 'تم حذف الشراء بنجاح'
        );
    }
}
