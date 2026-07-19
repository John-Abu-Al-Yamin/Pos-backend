<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkupSetting extends Model
{
    protected $fillable = [
        'product_type',
        'profit_percentage',
    ];

    protected $casts = [
        'profit_percentage' => 'decimal:2',
    ];
}
