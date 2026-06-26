<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProductPerformanceService
{
    public function getPerformance(?string $from, ?string $to, int $limit = 10): array
    {
        $bestSelling = $this->bestSelling($from, $to, $limit);
        $worstSelling = $this->worstSelling($from, $to, $limit);

        return compact('bestSelling', 'worstSelling');
    }

    private function bestSelling(?string $from, ?string $to, int $limit): array
    {
        return DB::table('products')
            ->select([
                'products.id',
                'products.name',
                'products.is_serialized',
                'categories.name as category_name',
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as total_sold'),
            ])
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->when($from, fn ($q) => $q->whereDate('sales.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sales.date', '<=', $to))
            ->groupBy('products.id', 'products.name', 'products.is_serialized', 'categories.name')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function worstSelling(?string $from, ?string $to, int $limit): array
    {
        $sub = DB::table('sale_items')
            ->select('product_id', DB::raw('SUM(quantity) as total'))
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->when($from, fn ($q) => $q->whereDate('sales.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sales.date', '<=', $to))
            ->groupBy('product_id');

        return DB::table('products')
            ->select([
                'products.id',
                'products.name',
                'products.is_serialized',
                'categories.name as category_name',
                DB::raw('COALESCE(sold.total, 0) as total_sold'),
            ])
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoinSub($sub, 'sold', 'products.id', '=', 'sold.product_id')
            ->orderBy('total_sold')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
