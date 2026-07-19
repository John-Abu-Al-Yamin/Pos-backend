<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pos\CheckoutRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Services\Pricing\PricingService;
use App\Services\Sales\SalesCheckoutService;
use Illuminate\Http\Request;


class PosController extends Controller
{
    public function __construct(
        private SalesCheckoutService $salesCheckoutService,
        private PricingService $pricingService,
    ) {}

    public function index(Request $request)
    {
        $query = $request->get('query');
        $type = $request->get('type', 'all');

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

        if ($type === 'new_mobile') {
            $mobiles->where(function ($q) {
                $q->where('source', 'new_purchase')
                    ->orWhere(function ($sq) {
                        $sq->whereNull('source')
                            ->whereNull('battery_health')
                            ->whereNull('screen_condition')
                            ->whereNull('body_condition');
                    });
            });
            $accessories->whereRaw('1 = 0');
        } elseif ($type === 'used_mobile') {
            $mobiles->where(function ($q) {
                $q->where('source', 'used_purchase')
                    ->orWhere(function ($sq) {
                        $sq->whereNull('source')
                            ->where(function ($ssq) {
                                $ssq->whereNotNull('battery_health')
                                    ->orWhereNotNull('screen_condition')
                                    ->orWhereNotNull('body_condition');
                            });
                    });
            });
            $accessories->whereRaw('1 = 0');
        } elseif ($type === 'accessory') {
            $mobiles->whereRaw('1 = 0');
            $accessories->whereHas('product', fn ($q) => $q->where('type', 'accessory'));
        } elseif ($type === 'spare_part') {
            $mobiles->whereRaw('1 = 0');
            $accessories->whereHas('product', fn ($q) => $q->where('type', 'spare_part'));
        }

        $mobilesCollection = $mobiles->get()->map(function (InventoryItem $item) {
            $pricing = $this->pricingService->calculateSellingPrice(
                $item->product,
                $item
            );

            $item->cost_price = $pricing['cost_price'];
            $item->suggested_price = $pricing['unit_price'];
            $item->profit_percentage = $pricing['profit_percentage'];

            return $item;
        });

        $accessoriesCollection = $accessories->get()->map(function (InventoryQuantity $item) {
            $pricing = $this->pricingService->calculateSellingPrice(
                $item->product
            );

            $item->cost_price = $pricing['cost_price'];
            $item->suggested_price = $pricing['unit_price'];
            $item->profit_percentage = $pricing['profit_percentage'];

            return $item;
        });

        return ApiResponse::success(
            data: [
                'mobiles' => $mobilesCollection,
                'accessories' => $accessoriesCollection,
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
