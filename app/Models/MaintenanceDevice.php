<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceDevice extends Model
{
    protected $fillable = [
        'product_id',
        'device_type',
        'brand',
        'model',
        'serial_number',
        'color',
        'condition_notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function maintenanceHeaders()
    {
        return $this->hasMany(MaintenanceHeader::class, 'maintenance_device_id');
    }
}
