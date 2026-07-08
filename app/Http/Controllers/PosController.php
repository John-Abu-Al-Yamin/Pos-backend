<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pos\CheckoutRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use Illuminate\Http\Request;
use App\Services\Sales\SalesCheckoutService;


class PosController extends Controller
{
        public function __construct(
        private SalesCheckoutService $salesCheckoutService
    ) {}
    public function index(Request $request)
    {

        $query = $request->get('query');

        // Available Mobiles (New + Used)
        $mobiles = InventoryItem::query()
            ->with('product')
            ->where('status', 'available');

        // Available Accessories & Spare Parts
        $accessories = InventoryQuantity::query()
            ->with('product')
            ->where('quantity', '>', 0);

        if ($query) {

            $mobiles->whereHas('product', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            });

            $accessories->whereHas('product', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            });
        }

        return ApiResponse::success(
            data: [

                'mobiles' => $mobiles->get(),
                'accessories' => $accessories->get()
            ]
        );
    }

    public function checkout(CheckoutRequest $request)
{
    $sale = $this->salesCheckoutService->checkout($request->validated());

    return response()->json([
        'message' => 'Sale completed successfully',
        'data' => $sale,
    ], 201);
}
}
