<?php

namespace App\Services\Catalog;

/**
 * Dual-input pricing algorithm per docs/catalog-schema.md#pricing-algorithm.
 * Pure function. Caller is responsible for persistence.
 */
class PriceCalculator
{
    public static function calculate(?int $marketPrice, ?int $lowPrice, PricingRules $rules): ?int
    {
        if ($marketPrice === null) {
            return null;
        }

        $low = $lowPrice ?? $marketPrice;

        $input = $marketPrice > $rules->highPrice
            ? min($low, $marketPrice)
            : max($low, $marketPrice);

        $price = match (true) {
            $input > $rules->highPrice => $input - $rules->highOffset,
            $input >= $rules->basePrice => $input - $rules->marketOffset,
            default => $rules->basePrice,
        };

        return max($price, $rules->basePrice);
    }
}
