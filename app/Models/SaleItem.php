<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockItems()
    {
        return $this->belongsToMany(StockItem::class, 'sale_item_stock_item');
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class);
    }
}
