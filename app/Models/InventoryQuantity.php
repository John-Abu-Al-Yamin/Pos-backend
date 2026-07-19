<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryQuantity extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'cost_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'cost_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
