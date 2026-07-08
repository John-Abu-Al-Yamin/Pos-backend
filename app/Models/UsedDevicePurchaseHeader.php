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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedDevicePurchaseItems()
    {
        return $this->hasMany(UsedDevicePurchaseItem::class);
    }

    public function items()
    {
        return $this->usedDevicePurchaseItems();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
