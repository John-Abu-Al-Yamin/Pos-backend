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
        'serial_number',
        'battery_health',
        'screen_condition',
        'body_condition',
        'fingerprint_working',
        'face_id_working',
        'notes',
    ];

    protected $casts = [
        'battery_health' => 'integer',
        'fingerprint_working' => 'boolean',
        'face_id_working' => 'boolean',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
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
