<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'product_id',
        'internal_serial',
        'item_condition',
        'status',
        'cost_price',
        'battery_health',
        'screen_condition',
        'body_condition',
        'fingerprint_working',
        'face_id_working',
        'notes',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'battery_health' => 'integer',
        'fingerprint_working' => 'boolean',
        'face_id_working' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isSold(): bool
    {
        return $this->status === 'sold';
    }

    public function isUnderRepair(): bool
    {
        return $this->status === 'under_repair';
    }

    public function isUsed(): bool
    {
        return $this->item_condition === 'used';
    }

    public function isNew(): bool
    {
        return $this->item_condition === 'new';
    }
}
