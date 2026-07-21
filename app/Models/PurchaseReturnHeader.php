<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnHeader extends Model
{
    protected $fillable = [
        'purchase_header_id',
        'return_number',
        'supplier_id',
        'user_id',
        'total_refund_amount',
        'reason',
        'return_date',
    ];

    protected $casts = [
        'total_refund_amount' => 'decimal:2',
        'return_date' => 'date',
    ];

    public function purchaseHeader()
    {
        return $this->belongsTo(PurchaseHeader::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_header_id');
    }
}
