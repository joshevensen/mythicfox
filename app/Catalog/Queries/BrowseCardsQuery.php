<?php

namespace App\Catalog\Queries;

use App\Models\Card;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated parent-row query for the catalog browse page.
 *
 * One row per (set_id, product_name, number) — a card identity. Sums quantity
 * across condition variants. The heaviest read in the app, isolated here so it
 * can be tuned/replaced without touching the controller (per
 * docs/ux/catalog.md#things-to-consider).
 */
class BrowseCardsQuery
{
    public const SORTABLE_COLUMNS = [
        'product_name',
        'number',
        'set_name',
        'rarity',
        'total_qty',
    ];

    /**
     * @param  array{
     *     product_id?: int|null,
     *     set_ids?: list<int>,
     *     in_stock?: bool,
     *     sort?: string,
     *     dir?: 'asc'|'desc',
     *     per_page?: int,
     *     page?: int,
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 50;
        $page = $filters['page'] ?? 1;
        $sort = $filters['sort'] ?? 'product_name';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'product_name';
        }

        $query = $this->base($filters);

        if ($sort === 'set_name') {
            $query->orderBy('sets.name', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $query->orderBy('cards.set_id', 'asc')
            ->orderBy('cards.product_name', 'asc')
            ->orderBy('cards.number', 'asc');

        return $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page,
        );
    }

    /**
     * @param  array{
     *     product_id?: int|null,
     *     set_ids?: list<int>,
     *     in_stock?: bool,
     * }  $filters
     */
    private function base(array $filters): Builder
    {
        $query = Card::query()
            ->from('cards')
            ->join('sets', 'cards.set_id', '=', 'sets.id')
            ->select([
                'cards.set_id',
                'cards.product_name',
                'cards.number',
                'cards.rarity',
                'sets.name as set_name',
                'sets.product_id',
                DB::raw('COALESCE(SUM(inventory.quantity), 0) as total_qty'),
            ])
            ->leftJoin('inventory', 'inventory.card_id', '=', 'cards.id')
            ->groupBy([
                'cards.set_id',
                'cards.product_name',
                'cards.number',
                'cards.rarity',
                'sets.name',
                'sets.product_id',
            ]);

        if (! empty($filters['product_id'])) {
            $query->where('sets.product_id', $filters['product_id']);
        }

        if (! empty($filters['set_ids'])) {
            $query->whereIn('cards.set_id', $filters['set_ids']);
        }

        if (! empty($filters['in_stock'])) {
            $query->havingRaw('COALESCE(SUM(inventory.quantity), 0) > 0');
        }

        return $query;
    }

    /**
     * Loads the per-condition variants for a single parent row.
     *
     * @return array<int, array{condition: string, quantity: int, tcgplayer_id: int}>
     */
    public function variantsFor(int $setId, string $productName, string $number): array
    {
        return Card::query()
            ->from('cards')
            ->leftJoin('inventory', 'inventory.card_id', '=', 'cards.id')
            ->where('cards.set_id', $setId)
            ->where('cards.product_name', $productName)
            ->where('cards.number', $number)
            ->orderBy('cards.condition', 'asc')
            ->get([
                'cards.condition',
                'cards.tcgplayer_id',
                DB::raw('COALESCE(inventory.quantity, 0) as quantity'),
            ])
            ->map(fn ($row) => [
                'condition' => (string) $row->condition,
                'quantity' => (int) $row->quantity,
                'tcgplayer_id' => (int) $row->tcgplayer_id,
            ])
            ->all();
    }
}
