<?php

namespace App\Catalog;

use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Product summary shared by the nested catalog pages.
 *
 * Carries the display label and the pricing-staleness flag so every catalog
 * controller reports the same shape. A product is stale when it has never been
 * priced or was last priced more than STALE_DAYS days ago.
 */
final class CatalogProductPayload
{
    public const STALE_DAYS = 3;

    /**
     * @return array{id: int, label: string, priced_at: string|null, is_stale: bool}
     */
    public static function for(Product $product): array
    {
        $threshold = Carbon::now()->subDays(self::STALE_DAYS);

        return [
            'id' => (int) $product->id,
            'label' => $product->display_label,
            'priced_at' => $product->priced_at?->toIso8601String(),
            'is_stale' => $product->priced_at === null || $product->priced_at->lt($threshold),
        ];
    }
}
