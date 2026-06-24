<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseHeader\StorePurchaseHeaderRequest;
use App\Http\Requests\PurchaseHeader\UpdatePurchaseHeaderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\PurchaseHeader;
use Illuminate\Http\Request;

class PurchaseHeaderController extends Controller
{
    //
    public function store(StorePurchaseHeaderRequest $request)
    {
        $data = $request->validated();
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

        $purchaseHeaders = PurchaseHeader::with(['supplier', 'purchaseItems.product', 'purchaseItems.stockItems'])
            ->paginate($perPage);

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
        $purchaseHeader = PurchaseHeader::find($id);

        if (!$purchaseHeader) {
            return ApiResponse::error(
                message: 'الشراء غير موجود',
                statusCode: 404
            );
        }

        $purchaseHeader->delete();
        return ApiResponse::success(
            message: 'تم حذف الشراء بنجاح'
        );
    }
}
