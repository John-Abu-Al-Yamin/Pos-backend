<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expense;
use App\Services\Expense\ExpenseService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseService $expenseService
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $query = Expense::query();

        if ($request->filled('expense_category')) {
            $query->where('expense_category', $request->input('expense_category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->input('to_date'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('notes', 'like', "%{$search}%");
        }

        $expenses = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب المصروفات بنجاح',
            data: $expenses
        );
    }

    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $expense = Expense::create($data);

        return ApiResponse::success(
            message: 'تم إنشاء المصروف بنجاح',
            data: $expense->fresh()
        );
    }

    public function show(int $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            data: $expense,
            message: 'تم جلب المصروف بنجاح'
        );
    }

    public function update(UpdateExpenseRequest $request, int $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404
            );
        }

        if (!$expense->isPending()) {
            return ApiResponse::error(
                message: 'لا يمكن تعديل مصروف تم دفعه أو إلغاؤه.',
                statusCode: 400
            );
        }

        $expense->update($request->validated());

        return ApiResponse::success(
            data: $expense->fresh(),
            message: 'تم تحديث المصروف بنجاح'
        );
    }

    public function destroy(int $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404
            );
        }

        if (!$expense->isPending()) {
            return ApiResponse::error(
                message: 'لا يمكن حذف مصروف تم دفعه أو إلغاؤه.',
                statusCode: 400
            );
        }

        $expense->delete();

        return ApiResponse::success(
            message: 'تم حذف المصروف بنجاح'
        );
    }

    public function pay(int $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404
            );
        }

        try {
            $expense = $this->expenseService->pay($expense);
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم دفع المصروف بنجاح',
            data: $expense->fresh()
        );
    }

    public function cancel(int $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404
            );
        }

        try {
            $expense = $this->expenseService->cancel($expense);
        } catch (\DomainException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                statusCode: 400
            );
        }

        return ApiResponse::success(
            message: 'تم إلغاء المصروف بنجاح',
            data: $expense->fresh()
        );
    }
}
