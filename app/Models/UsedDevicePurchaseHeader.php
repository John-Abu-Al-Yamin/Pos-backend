<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsedDevicePurchaseHeader extends Model
{
    //


    protected $fillable = [
        'purchase_number',
        'customer_id',
        'status',
        'total_amount',
        'created_by',
        'notes',
        'completed_at',
        'cancelled_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
