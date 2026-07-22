<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceUsedPart extends Model
{
    protected $fillable = [
        'maintenance_header_id',
        'product_id',
        'quantity',
        'cost_price',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function maintenanceHeader()
    {
        return $this->belongsTo(MaintenanceHeader::class, 'maintenance_header_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::saved(function ($part) {
            $part->maintenanceHeader->recalculateTotalCost();
        });

        static::deleted(function ($part) {
            $part->maintenanceHeader->recalculateTotalCost();
        });
    }
}
