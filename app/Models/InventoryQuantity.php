<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryQuantity extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
      
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
