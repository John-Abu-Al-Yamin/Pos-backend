<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_id',
        'sale_item_id',
        'stock_item_id',
        'product_id',
        'quantity',
        'refund_amount',
        'unit_cost',
        'total_cost',
        'unit_price',
        'condition_after_inspection',
        'restock',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'refund_amount' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'restock' => 'boolean',
        ];
    }

    public function returnHeader()
    {
        return $this->belongsTo(Returns::class, 'return_id');
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
