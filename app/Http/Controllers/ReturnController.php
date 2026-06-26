<?php

namespace App\Http\Controllers;

use App\Http\Requests\Return\StoreReturnRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Returns;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $returnService,
    ) {}

    public function store(StoreReturnRequest $request)
    {
        $data = $request->validated();
        $return = $this->returnService->createReturn($data, $request->user()->id);

        return ApiResponse::success(
            message: 'تم إنشاء المرتجع بنجاح',
            data: $return,
            statusCode: 201,
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $returns = Returns::with([
            'sale',
            'customer',
            'user',
            'returnItems.product',
        ])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'تم جلب المرتجعات بنجاح',
            data: $returns,
        );
    }

    public function show(int $id)
    {
        $return = Returns::with([
            'sale.customer',
            'sale.saleItems.product',
            'customer',
            'user',
            'returnItems.stockItem',
            'returnItems.product',
            'returnItems.saleItem',
        ])->find($id);

        if (!$return) {
            return ApiResponse::error(
                message: 'المرتجع غير موجود',
                statusCode: 404,
            );
        }

        return ApiResponse::success(
            message: 'تم جلب المرتجع بنجاح',
            data: $return,
        );
    }
}
