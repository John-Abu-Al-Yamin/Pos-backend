<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expense;
use App\Services\FinancialLedgerService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');

        $query = Expense::with('createdBy');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->to);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب المصروفات بنجاح',
            data: $expenses,
        );
    }

    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $expense = Expense::create($data);
        $this->ledger->recordExpensePayment($expense);
        $expense->load('createdBy');

        return ApiResponse::success(
            message: 'تم إنشاء المصروف بنجاح',
            data: $expense,
            statusCode: 201,
        );
    }

    public function show(int $id)
    {
        $expense = Expense::with('createdBy')->find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404,
            );
        }

        return ApiResponse::success(
            message: 'تم جلب المصروف بنجاح',
            data: $expense,
        );
    }

    public function update(UpdateExpenseRequest $request, int $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404,
            );
        }

        $data = $request->validated();
        $expense->update($data);
        $expense->load('createdBy');

        return ApiResponse::success(
            message: 'تم تحديث المصروف بنجاح',
            data: $expense,
        );
    }

    public function destroy(int $id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return ApiResponse::error(
                message: 'المصروف غير موجود',
                statusCode: 404,
            );
        }

        $expense->delete();

        return ApiResponse::success(
            message: 'تم حذف المصروف بنجاح',
        );
    }

    public function summary()
    {
        $today = now()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $monthEnd = now()->endOfMonth()->format('Y-m-d');

        $todayExpenses = Expense::whereDate('expense_date', $today)->sum('amount');
        $monthExpenses = Expense::whereDate('expense_date', '>=', $monthStart)
            ->whereDate('expense_date', '<=', $monthEnd)
            ->sum('amount');
        $totalExpenses = Expense::sum('amount');

        return ApiResponse::success(
            message: 'تم جلب ملخص المصروفات بنجاح',
            data: [
                'today' => (float) $todayExpenses,
                'thisMonth' => (float) $monthExpenses,
                'total' => (float) $totalExpenses,
            ],
        );
    }
}
