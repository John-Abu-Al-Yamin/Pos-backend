<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    //
    protected $fillable = [
        'purchase_header_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseHeader()
    {
        return $this->belongsTo(PurchaseHeader::class);
    }



    public function isMobile(): bool
    {
        return $this->product->type === 'mobile';
    }

    public function isQuantityProduct(): bool
    {
        return in_array($this->product->type, [
            'accessory',
            'spare_part'
        ]);
    }
}
