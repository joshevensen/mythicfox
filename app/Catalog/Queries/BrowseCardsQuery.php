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
 * from printings so the parent query stays narrow. Each row carries a computed
 * market_price: the LP market price (falling back to NM) of the first of the
 * card's non-foil, foil or etched printings that has one.
 */
class BrowseCardsQuery
{
    public const SORTABLE_COLUMNS = [
        'name',
        'number',
        'set_name',
        'rarity',
        'market_price',
    ];

    private const CONDITION_PRICE_COLUMNS = [
        'nm_market_price',
        'nm_low_price',
        'lp_market_price',
        'lp_low_price',
        'mp_market_price',
        'mp_low_price',
    ];

    /**
     * Correlated subquery for a card's headline market price. Repeated in ORDER BY
     * because PostgreSQL rejects output aliases inside ORDER BY expressions.
     */
    private const MARKET_PRICE_SQL = <<<'SQL'
        (SELECT COALESCE(p.lp_market_price, p.nm_market_price)
           FROM printings p
          WHERE p.card_id = cards.id
            AND p.finish IN ('non-foil', 'foil', 'etched')
            AND COALESCE(p.lp_market_price, p.nm_market_price) IS NOT NULL
          ORDER BY CASE p.finish WHEN 'non-foil' THEN 0 WHEN 'foil' THEN 1 ELSE 2 END
          LIMIT 1)
        SQL;

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
        } elseif ($sort === 'market_price') {
            $query->orderByRaw(self::MARKET_PRICE_SQL.' IS NULL')
                ->orderByRaw(self::MARKET_PRICE_SQL.' '.$dir);
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
            ])
            ->selectRaw(self::MARKET_PRICE_SQL.' as market_price');

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
     * @return array<int, array{finish: string, tcgplayer_id: int|null, nm_market_price: int|null, nm_low_price: int|null, lp_market_price: int|null, lp_low_price: int|null, mp_market_price: int|null, mp_low_price: int|null}>
     */
    public function variantsFor(int $cardId): array
    {
        return Printing::query()
            ->where('card_id', $cardId)
            ->orderBy('finish', 'asc')
            ->get(['finish', 'tcgplayer_id', ...self::CONDITION_PRICE_COLUMNS])
            ->map(function ($row) {
                $variant = [
                    'finish' => (string) $row->finish,
                    'tcgplayer_id' => $row->tcgplayer_id === null ? null : (int) $row->tcgplayer_id,
                ];

                foreach (self::CONDITION_PRICE_COLUMNS as $column) {
                    $variant[$column] = $row->{$column} === null ? null : (int) $row->{$column};
                }

                return $variant;
            })
            ->all();
    }
}
