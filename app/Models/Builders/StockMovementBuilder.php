<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class StockMovementBuilder extends Builder
{
    public function update(array $values): int
    {
        throw new RuntimeException('Stock movements are append-only and cannot be updated.');
    }

    public function delete(): mixed
    {
        throw new RuntimeException('Stock movements are append-only and cannot be deleted.');
    }
}
