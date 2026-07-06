<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseHeader extends Model
{
    //
    protected $fillable = [
        'supplier_id',
        'status',
        'created_by',
        'total_amount',
        'notes',
        'purchaseHeader_number',
        'supplier_invoice_number',
        'completed_at',
        'cancelled_at',
    ];
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
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
