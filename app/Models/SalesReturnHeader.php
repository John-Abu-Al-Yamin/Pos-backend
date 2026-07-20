<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnHeader extends Model
{
    protected $fillable = [
        'sales_header_id',
        'return_number',
        'customer_id',
        'user_id',
        'status',
        'total_refund_amount',
        'reason',
        'return_date',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'total_refund_amount' => 'decimal:2',
        'return_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function salesHeader()
    {
        return $this->belongsTo(SalesHeader::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class, 'sales_return_header_id');
    }
}
