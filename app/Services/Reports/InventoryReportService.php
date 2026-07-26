<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class InventoryReportService
{
    public function generate(array $filters): array
    {
        $categoryId = $filters['category_id'] ?? null;
        $brandId = $filters['brand_id'] ?? null;
        $productType = $filters['product_type'] ?? null;

        $movementDateFrom = isset($filters['movement_date_from'])
            ? Carbon::parse($filters['movement_date_from'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $movementDateTo = isset($filters['movement_date_to'])
            ? Carbon::parse($filters['movement_date_to'])->endOfDay()
            : now()->endOfDay();

        return [
            'stock_value' => $this->getStockValue($categoryId, $brandId, $productType),
            'stock_summary' => $this->getStockSummary($categoryId, $brandId, $productType),
            'low_stock' => $this->getLowStock($categoryId, $brandId),
            'by_product_type' => $this->getByProductType($categoryId, $brandId),
            'stock_movement_summary' => $this->getStockMovementSummary(
                $movementDateFrom,
                $movementDateTo,
                $filters['movement_type'] ?? null,
            ),
        ];
    }

    public function getStockValue(
        ?int $categoryId = null,
        ?int $brandId = null,
        ?string $productType = null,
    ): array {
        $mobileQuery = DB::table('inventory_items')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->where('inventory_items.status', 'available');

        if ($categoryId) {
            $mobileQuery->where('products.category_id', $categoryId);
        }
        if ($brandId) {
            $mobileQuery->where('products.brand_id', $brandId);
        }
        if ($productType) {
            $mobileQuery->where('products.type', $productType);
        }

        $mobileValue = (object) (clone $mobileQuery)
            ->selectRaw('
                COUNT(*) as total_items,
                COALESCE(SUM(inventory_items.cost_price), 0) as total_value
            ')
            ->first();

        $bulkQuery = DB::table('inventory_quantities')
            ->join('products', 'products.id', '=', 'inventory_quantities.product_id')
            ->where('inventory_quantities.quantity', '>', 0);

        if ($categoryId) {
            $bulkQuery->where('products.category_id', $categoryId);
        }
        if ($brandId) {
            $bulkQuery->where('products.brand_id', $brandId);
        }
        if ($productType) {
            $bulkQuery->where('products.type', $productType);
        }

        $bulkValue = (object) (clone $bulkQuery)
            ->selectRaw('
                COALESCE(SUM(inventory_quantities.quantity), 0) as total_quantity,
                COALESCE(SUM(inventory_quantities.quantity * inventory_quantities.cost_price), 0) as total_value
            ')
            ->first();

        $totalValue = (float) $mobileValue->total_value + (float) $bulkValue->total_value;

        return [
            'total_stock_value' => $totalValue,
            'mobile_devices' => [
                'count' => (int) $mobileValue->total_items,
                'value' => (float) $mobileValue->total_value,
            ],
            'bulk_products' => [
                'quantity' => (float) $bulkValue->total_quantity,
                'value' => (float) $bulkValue->total_value,
            ],
        ];
    }

    public function getStockSummary(
        ?int $categoryId = null,
        ?int $brandId = null,
        ?string $productType = null,
    ): array {
        $availableItemsSub = DB::table('inventory_items')
            ->where('status', 'available')
            ->groupBy('product_id')
            ->selectRaw('
                product_id,
                COUNT(*) as mobile_available_count,
                COALESCE(SUM(cost_price), 0) as mobile_stock_value,
                COALESCE(AVG(cost_price), 0) as mobile_avg_cost_price
            ');

        $query = DB::table('products')
            ->leftJoin('inventory_quantities', 'inventory_quantities.product_id', '=', 'products.id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($availableItemsSub, 'available_items', fn ($join) =>
                $join->on('products.id', '=', 'available_items.product_id')
            )
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.type as product_type,
                products.min_stock,
                COALESCE(inventory_quantities.quantity, 0) as stock_quantity,
                COALESCE(inventory_quantities.cost_price, 0) as avg_cost_price,
                COALESCE(available_items.mobile_available_count, 0) as mobile_available_count,
                COALESCE(available_items.mobile_stock_value, 0) as mobile_stock_value,
                COALESCE(available_items.mobile_avg_cost_price, 0) as mobile_avg_cost_price
            ');

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }
        if ($brandId) {
            $query->where('products.brand_id', $brandId);
        }
        if ($productType) {
            $query->where('products.type', $productType);
        }

        $products = $query->get();

        $totalItems = $products->sum(function ($p) {
            return $p->product_type === 'mobile'
                ? (int) $p->mobile_available_count
                : (float) $p->stock_quantity;
        });

        return [
            'total_products' => $products->count(),
            'total_stock_items' => $totalItems,
            'products' => $products->map(function ($p) {
                $availableQty = $p->product_type === 'mobile'
                    ? (int) $p->mobile_available_count
                    : (float) $p->stock_quantity;
                $stockValue = $p->product_type === 'mobile'
                    ? (float) $p->mobile_stock_value
                    : (float) $p->stock_quantity * (float) $p->avg_cost_price;
                $avgCost = $p->product_type === 'mobile'
                    ? (float) $p->mobile_avg_cost_price
                    : (float) $p->avg_cost_price;

                return [
                    'product_id' => (int) $p->product_id,
                    'product_name' => $p->product_name,
                    'product_type' => $p->product_type,
                    'available_quantity' => $availableQty,
                    'min_stock' => (int) $p->min_stock,
                    'avg_cost_price' => $avgCost,
                    'stock_value' => round($stockValue, 2),
                    'is_low_stock' => $p->product_type !== 'mobile' && (float) $p->stock_quantity <= (int) $p->min_stock,
                ];
            })->toArray(),
        ];
    }

    public function getLowStock(?int $categoryId = null, ?int $brandId = null): array
    {
        $lowStockQuery = DB::table('inventory_quantities')
            ->join('products', 'products.id', '=', 'inventory_quantities.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereColumn('inventory_quantities.quantity', '<=', 'products.min_stock')
            ->where('inventory_quantities.quantity', '>', 0)
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.type as product_type,
                categories.name as category_name,
                inventory_quantities.quantity as current_stock,
                products.min_stock,
                inventory_quantities.cost_price as avg_cost_price
            ')
            ->orderByRaw('(products.min_stock - inventory_quantities.quantity) DESC');

        $outOfStockQuery = DB::table('inventory_quantities')
            ->join('products', 'products.id', '=', 'inventory_quantities.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('inventory_quantities.quantity', '<=', 0)
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.type as product_type,
                categories.name as category_name,
                inventory_quantities.quantity as current_stock,
                products.min_stock,
                inventory_quantities.cost_price as avg_cost_price
            ')
            ->orderBy('products.name');

        if ($categoryId) {
            $lowStockQuery->where('products.category_id', $categoryId);
            $outOfStockQuery->where('products.category_id', $categoryId);
        }
        if ($brandId) {
            $lowStockQuery->where('products.brand_id', $brandId);
            $outOfStockQuery->where('products.brand_id', $brandId);
        }

        $lowStock = $lowStockQuery->get();
        $outOfStock = $outOfStockQuery->get();

        return [
            'low_stock_count' => $lowStock->count(),
            'low_stock_products' => $lowStock->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'product_type' => $item->product_type,
                'category_name' => $item->category_name,
                'current_stock' => (float) $item->current_stock,
                'min_stock' => (int) $item->min_stock,
                'avg_cost_price' => (float) $item->avg_cost_price,
            ])->values()->toArray(),
            'out_of_stock_count' => $outOfStock->count(),
            'out_of_stock_products' => $outOfStock->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'product_type' => $item->product_type,
                'category_name' => $item->category_name,
                'current_stock' => (float) $item->current_stock,
                'min_stock' => (int) $item->min_stock,
            ])->values()->toArray(),
        ];
    }

    public function getByProductType(?int $categoryId = null, ?int $brandId = null): array
    {
        $availableSub = DB::table('inventory_items')
            ->where('status', 'available')
            ->groupBy('product_id')
            ->selectRaw('
                product_id,
                COUNT(*) as cnt,
                COALESCE(SUM(cost_price), 0) as stock_value
            ');

        $query = DB::table('products')
            ->leftJoin('inventory_quantities', 'inventory_quantities.product_id', '=', 'products.id')
            ->leftJoinSub($availableSub, 'avail', fn ($join) =>
                $join->on('products.id', '=', 'avail.product_id')
            )
            ->selectRaw('
                products.type as product_type,
                COUNT(DISTINCT products.id) as product_count,
                COALESCE(SUM(inventory_quantities.quantity), 0) as total_quantity,
                COALESCE(SUM(CASE WHEN products.type = ? THEN avail.stock_value ELSE inventory_quantities.quantity * inventory_quantities.cost_price END), 0) as stock_value,
                COALESCE(SUM(CASE WHEN products.type = ? THEN avail.cnt ELSE 0 END), 0) as mobile_available
            ', ['mobile', 'mobile'])
            ->groupBy('products.type');

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }
        if ($brandId) {
            $query->where('products.brand_id', $brandId);
        }

        return $query->get()->map(function ($item) {
            return [
                'product_type' => $item->product_type,
                'product_count' => (int) $item->product_count,
                'total_quantity' => $item->product_type === 'mobile'
                    ? (int) ($item->mobile_available ?? 0)
                    : (float) $item->total_quantity,
                'stock_value' => (float) $item->stock_value,
            ];
        })->values()->toArray();
    }

    public function getStockMovementSummary(Carbon $dateFrom, Carbon $dateTo, ?string $movementType = null): array
    {
        $query = DB::table('stock_movements')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('
                movement_type,
                movement,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(quantity * unit_cost), 0) as total_value,
                COUNT(*) as transaction_count
            ')
            ->groupBy('movement_type', 'movement')
            ->orderBy('movement_type');

        if ($movementType) {
            $query->where('movement_type', $movementType);
        }

        $movements = $query->get();

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'movements' => $movements->map(fn ($item) => [
                'movement_type' => $item->movement_type,
                'direction' => $item->movement,
                'total_quantity' => (float) $item->total_quantity,
                'total_value' => (float) $item->total_value,
                'transaction_count' => (int) $item->transaction_count,
            ])->values()->toArray(),
            'net_inflow' => $this->calculateNetMovement($movements),
        ];
    }

    private function calculateNetMovement($movements): float
    {
        $net = 0;
        foreach ($movements as $m) {
            if ($m->movement === 'in') {
                $net += (float) $m->total_quantity;
            } else {
                $net -= (float) $m->total_quantity;
            }
        }
        return $net;
    }
}
