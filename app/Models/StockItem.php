<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = [
        'product_id',
        'purchase_item_id',
        'serial_number',
        'cost_price',
        'condition',
        'status',
        'battery_health',
        'screen_condition',
        'body_condition',
        'accessories',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'battery_health' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->belongsToMany(SaleItem::class, 'sale_item_stock_item');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}
