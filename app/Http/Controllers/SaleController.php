<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Returns;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {}

    public function store(StoreSaleRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $data['user_id'] = $user->id;
        $data['created_by_name'] = $user->role === 'admin'
            ? User::where('role', 'admin')->value('name')
            : $user->name;
        $sale = $this->saleService->createSale($data);

        return ApiResponse::success(
            message: 'تم إنشاء البيع بنجاح',
            data: $sale,
            statusCode: 201,
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $paymentMethod = $request->input('payment_method');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Sale::with(['customer', 'saleItems.product', 'saleItems.stockItems']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('saleItems.product', fn($p) => $p->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('saleItems.stockItems', fn($s) => $s->where('serial_number', 'like', "%{$search}%"));
            });
        }

        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $sales = $query->orderBy('id', 'desc')->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب المبيعات بنجاح',
            data: $sales,
        );
    }

    public function show(int $id)
    {
        $sale = Sale::with(['customer', 'saleItems.product', 'saleItems.stockItems', 'user'])->find($id);

        if (!$sale) {
            return ApiResponse::error(
                message: 'البيع غير موجود',
                statusCode: 404,
            );
        }

        return ApiResponse::success(
            message: 'تم جلب البيع بنجاح',
            data: $sale,
        );
    }

    public function destroy(int $id)
    {
        $sale = Sale::with('returns')->find($id);

        if (!$sale) {
            return ApiResponse::error(
                message: 'البيع غير موجود',
                statusCode: 404,
            );
        }

        if ($sale->returns()->exists()) {
            return ApiResponse::error(
                message: 'لا يمكن حذف الفاتورة لأنها تحتوي على مرتجعات. قم بإلغاء المرتجعات أولاً.',
                statusCode: 422,
            );
        }

        try {
            $sale->delete();
        } catch (QueryException $e) {
            return ApiResponse::error(
                message: 'لا يمكن حذف الفاتورة لأنها مرتبطة ببيانات أخرى.',
                statusCode: 422,
            );
        }

        return ApiResponse::success(
            message: 'تم حذف البيع بنجاح',
        );
    }

    public function returnable(int $id)
    {
        $sale = Sale::with([
            'customer',
            'saleItems.product',
            'saleItems.stockItems' => function ($query) {
                $query->where('status', 'sold');
            },
            'saleItems.returnItems',
        ])->find($id);

        if (!$sale) {
            return ApiResponse::error(
                message: 'البيع غير موجود',
                statusCode: 404,
            );
        }

        $sale->saleItems->each(function ($item) {
            $totalSold = (int) $item->quantity;
            $alreadyReturned = (int) $item->returnItems->sum('quantity');
            $item->returnable_quantity = max(0, $totalSold - $alreadyReturned);
            $item->max_returnable = $item->returnable_quantity;
            $item->returnable_stock_items = $item->stockItems;
            unset($item->stockItems);
            unset($item->returnItems);
        });

        return ApiResponse::success(
            message: 'تم جلب بيانات المرتجع بنجاح',
            data: $sale,
        );
    }
}
