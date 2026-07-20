<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_header_id',
        'sales_item_id',
        'product_id',
        'inventory_item_id',
        'quantity',
        'unit_refund_amount',
        'total_refund',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_refund_amount' => 'decimal:2',
        'total_refund' => 'decimal:2',
    ];

    public function salesReturnHeader()
    {
        return $this->belongsTo(SalesReturnHeader::class, 'sales_return_header_id');
    }

    public function salesItem()
    {
        return $this->belongsTo(SalesItem::class);
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
