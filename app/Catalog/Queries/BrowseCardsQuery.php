<?php

namespace App\Catalog\Queries;

use App\Models\Card;
use App\Models\Printing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregated parent-row query for the catalog browse page.
 *
 * One row per canonical card identity. Finish variants are loaded separately
 * from printings so the parent query stays narrow.
 */
class BrowseCardsQuery
{
    public const SORTABLE_COLUMNS = [
        'name',
        'number',
        'set_name',
        'rarity',
    ];

    /**
     * @param  array{
     *     product_id?: int|null,
     *     set_ids?: list<int>,
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
        $sort = $filters['sort'] ?? 'name';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'name';
        }

        $query = $this->base($filters);

        if ($sort === 'set_name') {
            $query->orderBy('sets.name', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $query->orderBy('cards.set_id', 'asc')
            ->orderBy('cards.name', 'asc')
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
     * }  $filters
     */
    private function base(array $filters): Builder
    {
        $query = Card::query()
            ->from('cards')
            ->join('sets', 'cards.set_id', '=', 'sets.id')
            ->select([
                'cards.id',
                'cards.set_id',
                'cards.name',
                'cards.number',
                'cards.rarity',
                'sets.name as set_name',
                'sets.product_id',
            ]);

        if (! empty($filters['product_id'])) {
            $query->where('sets.product_id', $filters['product_id']);
        }

        if (! empty($filters['set_ids'])) {
            $query->whereIn('cards.set_id', $filters['set_ids']);
        }

        return $query;
    }

    /**
     * Loads the per-finish variants for a single parent row.
     *
     * @return array<int, array{finish: string, tcgplayer_id: int|null, market_price: int|null, low_price: int|null}>
     */
    public function variantsFor(int $cardId): array
    {
        return Printing::query()
            ->where('card_id', $cardId)
            ->orderBy('finish', 'asc')
            ->get([
                'finish',
                'tcgplayer_id',
                'market_price',
                'low_price',
            ])
            ->map(fn ($row) => [
                'finish' => (string) $row->finish,
                'tcgplayer_id' => $row->tcgplayer_id === null ? null : (int) $row->tcgplayer_id,
                'market_price' => $row->market_price === null ? null : (int) $row->market_price,
                'low_price' => $row->low_price === null ? null : (int) $row->low_price,
            ])
            ->all();
    }
}
