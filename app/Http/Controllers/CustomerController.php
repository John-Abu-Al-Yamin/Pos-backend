<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $customers = Customer::orderBy('name')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب العملاء بنجاح',
            data: $customers,
        );
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $customer = Customer::create($data);

        return ApiResponse::success(
            message: 'تم إنشاء العميل بنجاح',
            data: $customer,
            statusCode: 201,
        );
    }

    public function show(int $id)
    {
        $customer = Customer::with('sales')->find($id);

        if (!$customer) {
            return ApiResponse::error(
                message: 'العميل غير موجود',
                statusCode: 404,
            );
        }

        return ApiResponse::success(
            message: 'تم جلب العميل بنجاح',
            data: $customer,
        );
    }

    public function update(UpdateCustomerRequest $request, int $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return ApiResponse::error(
                message: 'العميل غير موجود',
                statusCode: 404,
            );
        }

        $data = $request->validated();
        $customer->update($data);

        return ApiResponse::success(
            message: 'تم تحديث العميل بنجاح',
            data: $customer,
        );
    }

    public function destroy(int $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return ApiResponse::error(
                message: 'العميل غير موجود',
                statusCode: 404,
            );
        }

        $customer->delete();

        return ApiResponse::success(
            message: 'تم حذف العميل بنجاح',
        );
    }
}
