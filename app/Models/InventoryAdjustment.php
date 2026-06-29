<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'quantity_before',
        'quantity_after',
        'difference',
        'total_loss_amount',
        'total_gain_amount',
        'unit_cost_snapshot',
        'reason',
        'notes',
        'created_by',
        'voided_at',
        'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'voided_at' => 'datetime',
            'total_loss_amount' => 'decimal:2',
            'total_gain_amount' => 'decimal:2',
            'unit_cost_snapshot' => 'decimal:2',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
