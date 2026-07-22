<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceOperation extends Model
{
    protected $fillable = [
        'maintenance_header_id',
        'description',
        'operation_date',
        'technician',
        'cost',
        'notes',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function maintenanceHeader()
    {
        return $this->belongsTo(MaintenanceHeader::class, 'maintenance_header_id');
    }

    protected static function booted()
    {
        static::saved(function ($operation) {
            $operation->maintenanceHeader->recalculateTotalCost();
        });

        static::deleted(function ($operation) {
            $operation->maintenanceHeader->recalculateTotalCost();
        });
    }
}
