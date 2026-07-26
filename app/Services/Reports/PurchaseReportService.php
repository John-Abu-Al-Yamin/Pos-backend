<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PurchaseReportService
{
    public function generate(array $filters): array
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
        $supplierId = $filters['supplier_id'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $userId = $filters['created_by'] ?? null;

        return [
            'summary' => $this->getSummary($dateFrom, $dateTo, $supplierId, $productId, $userId),
            'by_product' => $this->getByProduct($dateFrom, $dateTo, $supplierId, $productId, $userId),
            'by_supplier' => $this->getBySupplier($dateFrom, $dateTo, $supplierId, $userId),
            'by_period' => $this->getByPeriod($dateFrom, $dateTo, $filters['group_by'] ?? 'day', $supplierId, $productId, $userId),
            'returns_summary' => $this->getReturnsSummary($dateFrom, $dateTo, $supplierId, $productId, $userId),
        ];
    }

    public function getSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $supplierId = null,
        ?int $productId = null,
        ?int $userId = null,
    ): array {
        $movementQuery = $this->netPurchaseMovementQuery($dateFrom, $dateTo, $supplierId, $productId, $userId);

        $summary = (object) DB::query()->fromSub($movementQuery, 'net_purchases')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN source = 'purchase' THEN amount ELSE 0 END), 0) as total_amount,
                COALESCE(SUM(CASE WHEN source = 'return' THEN -amount ELSE 0 END), 0) as total_refund,
                COALESCE(SUM(CASE WHEN source = 'purchase' THEN quantity ELSE 0 END), 0) as total_quantity,
                COALESCE(SUM(CASE WHEN source = 'return' THEN -quantity ELSE 0 END), 0) as returned_quantity,
                COALESCE(SUM(amount), 0) as net_amount,
                COUNT(DISTINCT CASE WHEN source = 'purchase' THEN transaction_id END) as transaction_count
            ")
            ->first();

        return [
            'total_purchase_amount' => (float) $summary->total_amount,
            'total_return_refund' => (float) $summary->total_refund,
            'net_purchase_amount' => round((float) $summary->net_amount, 2),
            'transaction_count' => (int) $summary->transaction_count,
            'total_quantity_purchased' => (float) $summary->total_quantity,
            'total_returned_quantity' => (float) $summary->returned_quantity,
        ];
    }

    public function getByProduct(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $supplierId = null,
        ?int $productId = null,
        ?int $userId = null,
    ): array {
        $query = $this->netPurchaseMovementQuery($dateFrom, $dateTo, $supplierId, $productId, $userId);

        return DB::query()->fromSub($query, 'net_purchases')
            ->selectRaw('
                product_id,
                product_name,
                product_type,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(amount), 0) as total_amount
            ')
            ->groupBy('product_id', 'product_name', 'product_type')
            ->orderByDesc('total_amount')
            ->get()->map(function ($item) {
            return [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'product_type' => $item->product_type,
                'total_quantity' => (float) $item->total_quantity,
                'total_amount' => (float) $item->total_amount,
            ];
        })->toArray();
    }

    public function getBySupplier(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $supplierId = null,
        ?int $userId = null,
    ): array {
        $query = $this->netPurchaseMovementQuery($dateFrom, $dateTo, $supplierId, null, $userId);

        return DB::query()->fromSub($query, 'net_purchases')
            ->selectRaw("
                supplier_id,
                supplier_name,
                COUNT(DISTINCT CASE WHEN source = 'purchase' THEN transaction_id END) as transaction_count,
                COALESCE(SUM(amount), 0) as total_amount
            ")
            ->groupBy('supplier_id', 'supplier_name')
            ->orderByDesc('total_amount')
            ->get()->map(function ($item) {
            return [
                'supplier_id' => (int) $item->supplier_id,
                'supplier_name' => $item->supplier_name,
                'transaction_count' => (int) $item->transaction_count,
                'total_amount' => (float) $item->total_amount,
            ];
        })->toArray();
    }

    public function getByPeriod(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $groupBy = 'day',
        ?int $supplierId = null,
        ?int $productId = null,
        ?int $userId = null,
    ): array {
        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'year' => '%Y',
            'week' => '%x-%v',
            default => '%Y-%m-%d',
        };

        $query = $this->netPurchaseMovementQuery($dateFrom, $dateTo, $supplierId, $productId, $userId, $dateFormat);

        return DB::query()->fromSub($query, 'net_purchases')
            ->selectRaw("
                period,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COUNT(DISTINCT CASE WHEN source = 'purchase' THEN transaction_id END) as transaction_count
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get()->map(function ($item) {
            return [
                'period' => $item->period,
                'total_amount' => (float) $item->total_amount,
                'total_quantity' => (float) $item->total_quantity,
                'transaction_count' => (int) $item->transaction_count,
            ];
        })->toArray();
    }

    public function getReturnsSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $supplierId = null,
        ?int $productId = null,
        ?int $userId = null,
    ): array
    {
        $query = DB::table('purchase_return_headers')
            ->join('purchase_return_items', 'purchase_return_items.purchase_return_header_id', '=', 'purchase_return_headers.id')
            ->where('purchase_return_headers.return_date', '>=', $dateFrom->toDateString())
            ->where('purchase_return_headers.return_date', '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw('
                COALESCE(SUM(purchase_return_items.quantity), 0) as total_returned_quantity,
                COALESCE(SUM(purchase_return_items.total_refund), 0) as total_refund,
                COUNT(DISTINCT purchase_return_headers.id) as return_transaction_count
            ');

        if ($supplierId) {
            $query->where('purchase_return_headers.supplier_id', $supplierId);
        }
        if ($productId) {
            $query->where('purchase_return_items.product_id', $productId);
        }
        if ($userId) {
            $query->where('purchase_return_headers.user_id', $userId);
        }

        $returns = (object) $query->first();

        return [
            'total_returned_quantity' => (float) $returns->total_returned_quantity,
            'total_refund' => (float) $returns->total_refund,
            'return_transaction_count' => (int) $returns->return_transaction_count,
        ];
    }

    private function netPurchaseMovementQuery(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $supplierId = null,
        ?int $productId = null,
        ?int $userId = null,
        ?string $dateFormat = null,
    ) {
        $purchasePeriod = $dateFormat
            ? $this->datePeriodExpression('purchase_headers.completed_at', $dateFormat)
            : 'NULL';

        $purchases = DB::table('purchase_items')
            ->join('purchase_headers', 'purchase_headers.id', '=', 'purchase_items.purchase_header_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_headers.supplier_id')
            ->where('purchase_headers.status', 'completed')
            ->whereBetween('purchase_headers.completed_at', [$dateFrom, $dateTo])
            ->selectRaw("
                'purchase' as source,
                purchase_headers.id as transaction_id,
                purchase_headers.supplier_id,
                suppliers.name as supplier_name,
                purchase_items.product_id,
                products.name as product_name,
                products.type as product_type,
                purchase_items.quantity as quantity,
                purchase_items.total_price as amount,
                $purchasePeriod as period
            ");

        $usedPurchasePeriod = $dateFormat
            ? $this->datePeriodExpression('used_device_purchase_headers.completed_at', $dateFormat)
            : 'NULL';

        $usedPurchases = DB::table('used_device_purchase_items')
            ->join('used_device_purchase_headers', 'used_device_purchase_headers.id', '=', 'used_device_purchase_items.used_device_purchase_header_id')
            ->join('products', 'products.id', '=', 'used_device_purchase_items.product_id')
            ->where('used_device_purchase_headers.status', 'completed')
            ->whereBetween('used_device_purchase_headers.completed_at', [$dateFrom, $dateTo])
            ->selectRaw("
                'purchase' as source,
                -used_device_purchase_headers.id as transaction_id,
                NULL as supplier_id,
                'Used Device Purchases' as supplier_name,
                used_device_purchase_items.product_id,
                products.name as product_name,
                products.type as product_type,
                used_device_purchase_items.quantity as quantity,
                used_device_purchase_items.total_price as amount,
                $usedPurchasePeriod as period
            ");

        if ($supplierId) {
            $purchases->where('purchase_headers.supplier_id', $supplierId);
            $usedPurchases->whereRaw('1 = 0');
        }
        if ($productId) {
            $purchases->where('purchase_items.product_id', $productId);
            $usedPurchases->where('used_device_purchase_items.product_id', $productId);
        }
        if ($userId) {
            $purchases->where('purchase_headers.created_by', $userId);
            $usedPurchases->where('used_device_purchase_headers.created_by', $userId);
        }

        $returnPeriod = $dateFormat
            ? $this->datePeriodExpression('purchase_return_headers.return_date', $dateFormat)
            : 'NULL';

        $returns = DB::table('purchase_return_items')
            ->join('purchase_return_headers', 'purchase_return_headers.id', '=', 'purchase_return_items.purchase_return_header_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_return_headers.supplier_id')
            ->join('products', 'products.id', '=', 'purchase_return_items.product_id')
            ->where('purchase_return_headers.return_date', '>=', $dateFrom->toDateString())
            ->where('purchase_return_headers.return_date', '<', $dateTo->copy()->addDay()->toDateString())
            ->selectRaw("
                'return' as source,
                purchase_return_headers.id as transaction_id,
                purchase_return_headers.supplier_id,
                suppliers.name as supplier_name,
                purchase_return_items.product_id,
                products.name as product_name,
                products.type as product_type,
                -purchase_return_items.quantity as quantity,
                -purchase_return_items.total_refund as amount,
                $returnPeriod as period
            ");

        if ($supplierId) {
            $returns->where('purchase_return_headers.supplier_id', $supplierId);
        }
        if ($productId) {
            $returns->where('purchase_return_items.product_id', $productId);
        }
        if ($userId) {
            $returns->where('purchase_return_headers.user_id', $userId);
        }

        return $purchases->unionAll($usedPurchases)->unionAll($returns);
    }

    private function datePeriodExpression(string $column, string $mysqlFormat): string
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return "DATE_FORMAT($column, '$mysqlFormat')";
        }

        $sqliteFormat = match ($mysqlFormat) {
            '%Y-%m' => '%Y-%m',
            '%Y' => '%Y',
            '%x-%v' => '%Y-%W',
            default => '%Y-%m-%d',
        };

        return "strftime('$sqliteFormat', $column)";
    }
}
