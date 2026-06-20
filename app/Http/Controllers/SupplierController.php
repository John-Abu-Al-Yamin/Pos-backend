<?php

namespace App\Http\Controllers;

use App\Http\Requests\Suppliers\StoreSuppliersRequest;
use App\Http\Requests\Suppliers\UpdateSuppliersRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function store(StoreSuppliersRequest $request)
    {
        $data = $request->validated();
        $supplier = Supplier::create($data);
        return ApiResponse::success(
            message: 'تم إنشاء المورد بنجاح',
            data: $supplier
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $suppliers = Supplier::paginate($perPage);
        return ApiResponse::success(
            message: 'تم جلب الموردين بنجاح',
            data: $suppliers
        );
    }

    public function show(int $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return ApiResponse::error(
                message: 'المورد غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            data: $supplier,
            message: 'تم جلب المورد بنجاح'
        );
    }

    public function update(UpdateSuppliersRequest $request, int $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return ApiResponse::error(
                message: 'المورد غير موجود',
                statusCode: 404
            );
        }

        $data = $request->validated();
        $supplier->update($data);

        return ApiResponse::success(
            data: $supplier,
            message: 'تم تحديث المورد بنجاح'
        );
    }

    public function destroy(int $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return ApiResponse::error(
                message: 'المورد غير موجود',
                statusCode: 404
            );
        }

        $supplier->delete();

        return ApiResponse::success(
            message: 'تم حذف المورد بنجاح'
        );
    }
}
