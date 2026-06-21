<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockItem;
use Illuminate\Support\Str;

class SerialNumberService
{
    public function generate(Product $product): string
    {
        do {
            $serialNumber = $this->buildSerialNumber($product);
        } while ($this->serialNumberExists($serialNumber));

        return $serialNumber;
    }

    public function generateForProduct(int $productId): string
    {
        $product = Product::findOrFail($productId);
        return $this->generate($product);
    }

    private function buildSerialNumber(Product $product): string
    {
        $prefix = 'SN';
        $productCode = str_pad($product->id, 4, '0', STR_PAD_LEFT);
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$productCode}-{$date}-{$random}";
    }

    private function serialNumberExists(string $serialNumber): bool
    {
        return StockItem::where('serial_number', $serialNumber)->exists();
    }
}
