<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_header_id',
        'purchase_item_id',
        'product_id',
        'inventory_item_id',
        'quantity',
        'unit_refund_amount',
        'total_refund',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_refund_amount' => 'decimal:2',
        'total_refund' => 'decimal:2',
    ];

    public function purchaseReturnHeader()
    {
        return $this->belongsTo(PurchaseReturnHeader::class, 'purchase_return_header_id');
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
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
