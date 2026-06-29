<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'category_id', 'is_serialized', 'min_stock', 'product_category'];

    protected function casts(): array
    {
        return [
            'is_serialized' => 'boolean',
            'min_stock' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
