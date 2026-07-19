<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    protected $table = 'sales_items';

    protected $fillable = [
        'sales_header_id',
        'product_id',
        'inventory_item_id',
        'quantity',
        'unit_price',
        'unit_cost',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function salesHeader()
    {
        return $this->belongsTo(SalesHeader::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}