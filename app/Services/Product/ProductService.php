<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            return Product::create($data);
        });
    }

    public function duplicateExists(string $name, int $categoryId): bool
    {
        return Product::query()
            ->where('name', $name)
            ->where('category_id', $categoryId)
            ->exists();
    }
}
