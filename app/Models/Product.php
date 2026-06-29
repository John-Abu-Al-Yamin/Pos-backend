<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //
    protected $fillable = ['name', 'category_id', 'is_serialized', 'selling_price', 'min_stock', 'product_category'];

    protected function casts(): array
    {
        return [
            'is_serialized' => 'boolean',
            'selling_price' => 'decimal:2',
            'min_stock' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }
}
