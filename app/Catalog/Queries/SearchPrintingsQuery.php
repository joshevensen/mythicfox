<?php

namespace App\Catalog\Queries;

use App\Models\Printing;
use App\Models\Product;

/**
 * Printing rows matching a card-name search across every product.
 *
 * One row per printing. Names that start with the term sort first, then name,
 * set name, collector number and finish priority (non-foil, foil, etched).
 * Ranking happens in SQL before the limit, so the returned rows are the best ones.
 */
class SearchPrintingsQuery
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    public const LIMIT = 20;

    /**
     * @return list<array{printing_id: int, card_id: int, product_id: int, name: string, product_label: string, set_name: string, number: string, finish: string}>
     */
    public function search(string $term): array
    {
        $escaped = addcslashes($term, '%_\\');

        return Printing::query()
            ->join('cards', 'printings.card_id', '=', 'cards.id')
            ->join('sets', 'cards.set_id', '=', 'sets.id')
            ->join('products', 'sets.product_id', '=', 'products.id')
            ->select([
                'printings.id as printing_id',
                'printings.finish',
                'cards.id as card_id',
                'cards.name',
                'cards.number',
                'sets.name as set_name',
                'products.id as product_id',
                'products.name as product_name',
                'products.display_name as product_display_name',
            ])
            ->whereLike('cards.name', '%'.$escaped.'%')
            ->orderByRaw('CASE WHEN LOWER(cards.name) LIKE LOWER(?) THEN 0 ELSE 1 END', [$escaped.'%'])
            ->orderBy('cards.name')
            ->orderBy('sets.name')
            ->orderBy('cards.number')
            ->orderByRaw("CASE printings.finish WHEN 'non-foil' THEN 0 WHEN 'foil' THEN 1 WHEN 'etched' THEN 2 ELSE 3 END")
            ->orderBy('printings.id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($row): array => [
                'printing_id' => (int) $row->printing_id,
                'card_id' => (int) $row->card_id,
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'product_label' => (string) (new Product([
                    'name' => $row->product_name,
                    'display_name' => $row->product_display_name,
                ]))->display_label,
                'set_name' => (string) $row->set_name,
                'number' => (string) $row->number,
                'finish' => (string) $row->finish,
            ])
            ->all();
    }
}
