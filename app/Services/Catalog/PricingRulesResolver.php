<?php

namespace App\Services\Catalog;

use App\Models\Card;
use App\Models\Product;
use App\Models\Set;

/**
 * Resolves the effective PricingRules for a card or set by walking up the
 * product hierarchy with per-field fallback.
 *
 * Per docs/catalog-schema.md#pricing-logic: "Each rule field on a set is
 * independently nullable: a non-null set value wins for that field; a null
 * set value falls back to the product's value for that same field. Fallback
 * is per-field, not all-or-nothing." The 10-007 task wording around "full
 * precedence" was about the absence of partial-inheritance hooks
 * (e.g. "use 75% of product offset"), not all-or-nothing per row.
 */
class PricingRulesResolver
{
    public static function forSet(Set $set): PricingRules
    {
        $product = $set->relationLoaded('product') ? $set->product : $set->product()->first();
        if (! $product instanceof Product) {
            throw new \RuntimeException("Set [{$set->id}] has no product");
        }

        return new PricingRules(
            basePrice: $set->base_price ?? $product->base_price,
            highPrice: $set->high_price ?? $product->high_price,
            marketOffset: $set->market_offset ?? $product->market_offset,
            highOffset: $set->high_offset ?? $product->high_offset,
        );
    }

    public static function forCard(Card $card): PricingRules
    {
        $set = $card->relationLoaded('set') ? $card->set : $card->set()->first();
        if (! $set instanceof Set) {
            throw new \RuntimeException("Card [{$card->id}] has no set");
        }

        return self::forSet($set);
    }
}
