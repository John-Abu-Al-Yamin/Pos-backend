<?php

namespace App\Services;

use App\Models\PurchaseHeader;
use App\Models\Sale;
use App\Models\Returns;
use Illuminate\Support\Facades\DB;

class FinancialService
{
    public function getMetrics(?string $from, ?string $to): array
    {
        $totalPurchases = $this->totalPurchases($from, $to);
        $totalSales = $this->totalSales($from, $to);
        $totalRefunds = $this->totalRefunds($from, $to);
        $cogs = $this->costOfGoodsSold($from, $to);

        return [
            'totalPurchases' => (float) $totalPurchases,
            'totalSales' => (float) $totalSales,
            'totalRefunds' => (float) $totalRefunds,
            'cashFlow' => (float) ($totalSales - $totalRefunds - $totalPurchases),
            'grossProfit' => (float) ($totalSales - $cogs - $totalRefunds),
        ];
    }

    private function totalPurchases(?string $from, ?string $to): float
    {
        return PurchaseHeader::when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->sum('total');
    }

    private function totalSales(?string $from, ?string $to): float
    {
        return Sale::when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->sum('total');
    }

    private function totalRefunds(?string $from, ?string $to): float
    {
        return Returns::when($from, fn ($q) => $q->whereDate('return_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('return_date', '<=', $to))
            ->sum('refund_total');
    }

    private function costOfGoodsSold(?string $from, ?string $to): float
    {
        return (float) DB::table('stock_items')
            ->join('sale_item_stock_item', 'stock_items.id', '=', 'sale_item_stock_item.stock_item_id')
            ->join('sale_items', 'sale_item_stock_item.sale_item_id', '=', 'sale_items.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('stock_items.status', 'sold')
            ->when($from, fn ($q) => $q->whereDate('sales.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sales.date', '<=', $to))
            ->sum('stock_items.cost_price');
    }
}
