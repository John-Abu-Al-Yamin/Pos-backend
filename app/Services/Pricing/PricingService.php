<?php

namespace App\Services\Pricing;

use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\MarkupSetting;
use App\Models\Product;

class PricingService
{
    private ?array $allMarkups = null;

    public function calculateSellingPrice(
        Product $product,
        ?InventoryItem $inventoryItem = null,
        ?float $costPrice = null
    ): array {
        $productType = $this->resolveProductType($product, $inventoryItem);
        $markup = $this->getMarkup($productType);
        $costPrice = $costPrice ?? $this->resolveCostPrice($product, $inventoryItem);
        $profitPercentage = $markup ? $markup->profit_percentage : 0;
        $unitPrice = $costPrice * (1 + $profitPercentage / 100);

        return [
            'unit_price' => round($unitPrice, 2),
            'cost_price' => round($costPrice, 2),
            'profit_percentage' => $profitPercentage,
        ];
    }

    public function resolveProductType(Product $product, ?InventoryItem $inventoryItem = null): string
    {
        if ($product->type === 'mobile') {
            if ($inventoryItem && $inventoryItem->source === 'used_purchase') {
                return 'used_mobile';
            }

            if ($inventoryItem && $inventoryItem->source === null) {
                $isLikelyUsed = $inventoryItem->battery_health !== null
                    || $inventoryItem->screen_condition !== null
                    || $inventoryItem->body_condition !== null;

                if ($isLikelyUsed) {
                    return 'used_mobile';
                }
            }

            return 'new_mobile';
        }

        return $product->type;
    }

    public function resolveCostPrice(Product $product, ?InventoryItem $inventoryItem = null): float
    {
        if ($inventoryItem) {
            return (float) $inventoryItem->cost_price;
        }

        if (in_array($product->type, ['accessory', 'spare_part'])) {
            $inventoryQuantity = $product->inventoryQuantity;
            if ($inventoryQuantity) {
                return (float) $inventoryQuantity->cost_price;
            }
        }

        return 0;
    }

    public function getMarkup(string $productType): ?MarkupSetting
    {
        if ($this->allMarkups === null) {
            $this->allMarkups = MarkupSetting::all()
                ->keyBy('product_type')
                ->toArray();
        }

        $markupData = $this->allMarkups[$productType] ?? null;

        if ($markupData) {
            $markup = new MarkupSetting();
            $markup->forceFill($markupData);
            return $markup;
        }

        return null;
    }
}
