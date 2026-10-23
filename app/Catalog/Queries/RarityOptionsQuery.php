<?php

namespace App\Catalog\Queries;

use App\Models\Card;
use Illuminate\Support\Facades\Cache;

/**
 * Rarity filter options for the catalog browse page, grouped by product.
 *
 * Cached because it scans every card; writers (CSV import, catalog sync)
 * call forget() once they finish changing cards.
 */
class RarityOptionsQuery
{
    public const CACHE_KEY = 'catalog:rarities_by_product';

    /**
     * Distinct non-empty rarities per product, alphabetical.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public function byProduct(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $rows = Card::query()
                ->join('sets', 'cards.set_id', '=', 'sets.id')
                ->where('cards.rarity', '!=', '')
                ->select(['sets.product_id', 'cards.rarity'])
                ->distinct()
                ->orderBy('sets.product_id')
                ->orderBy('cards.rarity')
                ->get();

            $grouped = [];

            foreach ($rows as $row) {
                $key = (string) $row->product_id;
                $grouped[$key] ??= [];
                $grouped[$key][] = [
                    'value' => (string) $row->rarity,
                    'label' => (string) $row->rarity,
                ];
            }

            return $grouped;
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
