<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {}

    public function store(StoreSaleRequest $request)
    {
        $data = $request->validated();
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

        $sales = Sale::with(['customer', 'saleItems.product'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب المبيعات بنجاح',
            data: $sales,
        );
    }

    public function show(int $id)
    {
        $sale = Sale::with(['customer', 'saleItems.product', 'saleItems.stockItems'])->find($id);

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
}
