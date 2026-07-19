<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'product_id',
        'internal_serial',
        'status',
        'source',
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
        'source' => 'string',
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

    public function isNewMobile(): bool
    {
        return $this->source === 'new_purchase' || $this->source === null;
    }

    public function isUsedMobile(): bool
    {
        return $this->source === 'used_purchase';
    }
}
