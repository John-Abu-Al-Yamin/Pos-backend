<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesHeader extends Model
{
    //
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'subtotal',
        'discount_amount',
        'total_amount',
        'notes',
        'created_by',
    ];

       protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function salesItems()
    {
        return $this->hasMany(SalesItem::class);
    }
}
