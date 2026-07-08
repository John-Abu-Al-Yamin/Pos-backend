<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsedDevicePurchaseItem extends Model
{
    //
    protected $fillable = [
        'product_id',
        'used_device_purchase_header_id',
        'quantity',
        'unit_price',
        'total_price',
        'screen_condition',
        'body_condition',
        'fingerprint_working',
        'face_id_working',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function usedDevicePurchaseHeader()
    {
        return $this->belongsTo(UsedDevicePurchaseHeader::class);
    }

    public function purchaseHeader()
    {
        return $this->usedDevicePurchaseHeader();
    }
}
