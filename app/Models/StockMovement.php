<?php

namespace App\Models;

use App\Models\Builders\StockMovementBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'inventory_item_id',
        'movement_type',
        'movement',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Stock movements are append-only and cannot be updated.'));
        static::deleting(fn () => throw new RuntimeException('Stock movements are append-only and cannot be deleted.'));
    }

    public function newEloquentBuilder($query): Builder
    {
        return new StockMovementBuilder($query);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
