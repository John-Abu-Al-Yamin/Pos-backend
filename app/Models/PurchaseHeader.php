<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseHeader extends Model
{
    //
    protected $fillable = [
        'supplier_id',
        'date',
        'total',
        'type'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
