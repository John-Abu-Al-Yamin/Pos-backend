<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_header_id',
        'product_id',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    public function purchaseHeader()
    {
        return $this->belongsTo(PurchaseHeader::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }
}
