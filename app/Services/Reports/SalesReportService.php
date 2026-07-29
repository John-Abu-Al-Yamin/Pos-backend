<?php

namespace App\Services\Reports;

use App\Models\SalesHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SalesReportService
{
    public function generate(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
        $categoryId = $filters['category_id'] ?? null;
        $brandId = $filters['brand_id'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $customerId = $filters['customer_id'] ?? null;
        $userId = $filters['created_by'] ?? null;
        $groupBy = $filters['group_by'] ?? 'day';

        return [
            'summary' => $this->getSummary($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId),
            'by_product' => $this->getByProduct($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId),
            'by_category' => $this->getByCategory($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId),
            'by_brand' => $this->getByBrand($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId),
            'by_period' => $this->getByPeriod($dateFrom, $dateTo, $groupBy, $categoryId, $brandId, $productId, $customerId, $userId),
            'best_selling' => $this->getBestSelling($dateFrom, $dateTo, 10, $categoryId, $brandId, $productId, $customerId, $userId),
            'returns_summary' => $this->getReturnsSummary($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId),
        ];
    }

    public function getSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): array {
        $movementQuery = $this->netSalesMovementQuery($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId);

        $summary = (object) DB::query()->fromSub($movementQuery, 'net_sales')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN source = 'sale' THEN gross_revenue ELSE 0 END), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN source = 'sale' THEN discount_amount ELSE 0 END), 0) as total_discount,
                COALESCE(SUM(CASE WHEN source = 'return' THEN -revenue ELSE 0 END), 0) as total_returns,
                COALESCE(SUM(CASE WHEN source = 'return' THEN -cogs ELSE 0 END), 0) as return_cogs,
                COALESCE(SUM(revenue), 0) as net_revenue,
                COALESCE(SUM(cogs), 0) as net_cogs,
                COALESCE(SUM(quantity), 0) as net_quantity,
                COUNT(DISTINCT CASE WHEN source = 'sale' THEN transaction_id END) as transaction_count
            ")
            ->first();

        return [
            'total_revenue' => (float) $summary->total_revenue,
            'total_discount' => (float) $summary->total_discount,
            'total_returns' => (float) $summary->total_returns,
            'net_revenue' => round((float) $summary->net_revenue, 2),
            'transaction_count' => (int) $summary->transaction_count,
            'total_quantity_sold' => (float) $summary->net_quantity,
            'return_cogs' => (float) $summary->return_cogs,
            'total_cogs' => round((float) $summary->net_cogs, 2),
            'gross_profit' => round((float) $summary->net_revenue - (float) $summary->net_cogs, 2),
            'average_transaction_value' => $summary->transaction_count > 0
                ? round((float) $summary->net_revenue / (int) $summary->transaction_count, 2)
                : 0,
        ];
    }

    public function getByProduct(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): array {
        $query = $this->netSalesMovementQuery($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId);

        return DB::query()->fromSub($query, 'net_sales')
            ->selectRaw('
                product_id,
                product_name,
                product_type,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(cogs), 0) as total_cogs,
                COUNT(DISTINCT CASE WHEN source = "sale" THEN transaction_id END) as transaction_count
            ')
            ->groupBy('product_id', 'product_name', 'product_type')
            ->orderByDesc('total_revenue')
            ->get()->map(function ($item) {
            $profit = (float) $item->total_revenue - (float) $item->total_cogs;
            return [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'product_type' => $item->product_type,
                'total_quantity' => (float) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
                'total_cogs' => (float) $item->total_cogs,
                'gross_profit' => round($profit, 2),
                'transaction_count' => (int) $item->transaction_count,
            ];
        })->toArray();
    }

    public function getByCategory(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): array {
        $query = $this->netSalesMovementQuery($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId);

        return DB::query()->fromSub($query, 'net_sales')
            ->selectRaw('
                category_id,
                category_name,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(cogs), 0) as total_cogs
            ')
            ->groupBy('category_id', 'category_name')
            ->get()->map(function ($item) {
            $profit = (float) $item->total_revenue - (float) $item->total_cogs;
            return [
                'category_id' => (int) $item->category_id,
                'category_name' => $item->category_name,
                'total_quantity' => (float) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
                'total_cogs' => (float) $item->total_cogs,
                'gross_profit' => round($profit, 2),
            ];
        })->toArray();
    }

    public function getByBrand(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): array {
        $query = $this->netSalesMovementQuery($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId);

        return DB::query()->fromSub($query, 'net_sales')
            ->whereNotNull('brand_id')
            ->selectRaw('
                brand_id,
                brand_name,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(cogs), 0) as total_cogs
            ')
            ->groupBy('brand_id', 'brand_name')
            ->get()->map(function ($item) {
            $profit = (float) $item->total_revenue - (float) $item->total_cogs;
            return [
                'brand_id' => (int) $item->brand_id,
                'brand_name' => $item->brand_name,
                'total_quantity' => (float) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
                'total_cogs' => (float) $item->total_cogs,
                'gross_profit' => round($profit, 2),
            ];
        })->toArray();
    }

    public function getByPeriod(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $groupBy = 'day',
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): array {
        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'year' => '%Y',
            'week' => '%x-%v',
            default => '%Y-%m-%d',
        };

        $query = $this->netSalesMovementQuery($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId, $dateFormat);

        return DB::query()->fromSub($query, 'net_sales')
            ->selectRaw("
                period,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(cogs), 0) as total_cogs,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COUNT(DISTINCT CASE WHEN source = 'sale' THEN transaction_id END) as transaction_count
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get()->map(function ($item) {
            $profit = (float) $item->total_revenue - (float) $item->total_cogs;
            return [
                'period' => $item->period,
                'total_revenue' => (float) $item->total_revenue,
                'total_cogs' => (float) $item->total_cogs,
                'gross_profit' => round($profit, 2),
                'total_quantity' => (float) $item->total_quantity,
                'transaction_count' => (int) $item->transaction_count,
            ];
        })->toArray();
    }

    public function getBestSelling(
        Carbon $dateFrom,
        Carbon $dateTo,
        int $limit = 10,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): array {
        $query = $this->netSalesMovementQuery($dateFrom, $dateTo, $categoryId, $brandId, $productId, $customerId, $userId);

        return DB::query()->fromSub($query, 'net_sales')
            ->selectRaw('
                product_id,
                product_name,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(revenue), 0) as total_revenue,
                COALESCE(SUM(cogs), 0) as total_cogs
            ')
            ->groupBy('product_id', 'product_name')
            ->havingRaw('SUM(quantity) > 0')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()->map(function ($item) {
            $profit = (float) $item->total_revenue - (float) $item->total_cogs;
            return [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'total_quantity' => (float) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
                'total_cogs' => (float) $item->total_cogs,
                'gross_profit' => round($profit, 2),
            ];
        })->toArray();
    }

    public function getReturnsSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): array
    {
        $query = DB::table('sales_return_headers')
            ->join('sales_return_items', 'sales_return_items.sales_return_header_id', '=', 'sales_return_headers.id')
            ->join('products', 'products.id', '=', 'sales_return_items.product_id')
            ->where('sales_return_headers.return_date', '>=', $dateFrom->toDateString())
            ->where('sales_return_headers.return_date', '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw('
                COALESCE(SUM(sales_return_items.quantity), 0) as total_returned_quantity,
                COALESCE(SUM(sales_return_items.total_refund), 0) as total_refund,
                COUNT(DISTINCT sales_return_headers.id) as return_transaction_count
            ');

        $this->applyReturnFilters($query, $categoryId, $brandId, $productId, $customerId, $userId);

        $returns = $query->first();

        return [
            'total_returned_quantity' => (float) $returns->total_returned_quantity,
            'total_refund' => (float) $returns->total_refund,
            'return_transaction_count' => (int) $returns->return_transaction_count,
        ];
    }

    public function getRecentSales(int $limit = 5, bool $includeFinancials = true): array
    {
        return SalesHeader::with('customer:id,name', 'createdBy:id,name')
            ->latest()
            ->limit($limit)
            ->get(['id', 'invoice_number', 'customer_id', 'total_amount', 'created_by', 'created_at'])
            ->map(function (SalesHeader $sale) use ($includeFinancials) {
                $item = [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer?->name,
                    'created_by' => $sale->createdBy?->name,
                    'created_at' => $sale->created_at?->toIso8601String(),
                ];

                if ($includeFinancials) {
                    $item['total_amount'] = (float) $sale->total_amount;
                }

                return $item;
            })
            ->toArray();
    }

    private function netSalesMovementQuery(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
        ?string $dateFormat = null,
    ) {
        $salePeriod = $dateFormat
            ? "DATE_FORMAT(sales_headers.created_at, '$dateFormat')"
            : 'NULL';

        $discountAllocation = $this->lineDiscountExpression('sales_items.total_price', 'sales_headers.subtotal', 'sales_headers.discount_amount');

        $sales = DB::table('sales_items')
            ->join('sales_headers', 'sales_headers.id', '=', 'sales_items.sales_header_id')
            ->join('products', 'products.id', '=', 'sales_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->whereBetween('sales_headers.created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                'sale' as source,
                sales_headers.id as transaction_id,
                sales_items.product_id,
                products.name as product_name,
                products.type as product_type,
                products.category_id,
                categories.name as category_name,
                products.brand_id,
                brands.name as brand_name,
                sales_items.quantity as quantity,
                sales_items.total_price as gross_revenue,
                $discountAllocation as discount_amount,
                sales_items.total_price - $discountAllocation as revenue,
                COALESCE(sales_items.unit_cost, 0) * sales_items.quantity as cogs,
                $salePeriod as period
            ");

        if ($productId) {
            $sales->where('sales_items.product_id', $productId);
        }
        if ($categoryId) {
            $sales->where('products.category_id', $categoryId);
        }
        if ($brandId) {
            $sales->where('products.brand_id', $brandId);
        }
        if ($customerId) {
            $sales->where('sales_headers.customer_id', $customerId);
        }
        if ($userId) {
            $sales->where('sales_headers.created_by', $userId);
        }

        $returnPeriod = $dateFormat
            ? "DATE_FORMAT(sales_return_headers.return_date, '$dateFormat')"
            : 'NULL';

        $returns = DB::table('sales_return_items')
            ->join('sales_return_headers', 'sales_return_headers.id', '=', 'sales_return_items.sales_return_header_id')
            ->join('products', 'products.id', '=', 'sales_return_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('sales_return_headers.return_date', '>=', $dateFrom->toDateString())
            ->where('sales_return_headers.return_date', '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw("
                'return' as source,
                sales_return_headers.id as transaction_id,
                sales_return_items.product_id,
                products.name as product_name,
                products.type as product_type,
                products.category_id,
                categories.name as category_name,
                products.brand_id,
                brands.name as brand_name,
                -sales_return_items.quantity as quantity,
                0 as gross_revenue,
                0 as discount_amount,
                -sales_return_items.total_refund as revenue,
                -(COALESCE(sales_return_items.unit_cost, 0) * sales_return_items.quantity) as cogs,
                $returnPeriod as period
            ");

        $this->applyReturnFilters($returns, $categoryId, $brandId, $productId, $customerId, $userId);

        return $sales->unionAll($returns);
    }

    private function lineDiscountExpression(string $lineTotalColumn, string $invoiceSubtotalColumn, string $invoiceDiscountColumn): string
    {
        return "CASE WHEN COALESCE($invoiceSubtotalColumn, 0) > 0 THEN COALESCE($invoiceDiscountColumn, 0) * (($lineTotalColumn * 1.0) / $invoiceSubtotalColumn) ELSE 0 END";
    }

    private function applyReturnFilters(
        $query,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $productId = null,
        ?int $customerId = null,
        ?int $userId = null,
    ): void {
        if ($productId) {
            $query->where('sales_return_items.product_id', $productId);
        }
        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }
        if ($brandId) {
            $query->where('products.brand_id', $brandId);
        }
        if ($customerId) {
            $query->where('sales_return_headers.customer_id', $customerId);
        }
        if ($userId) {
            $query->where('sales_return_headers.user_id', $userId);
        }
    }
}
