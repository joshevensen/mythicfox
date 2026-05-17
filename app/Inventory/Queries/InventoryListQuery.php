<?php

namespace App\Inventory\Queries;

use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Paginated inventory list for the /inventory page.
 *
 * One row per `inventory` record. The page enforces a Product+Set+Condition
 * required-filter contract; this query applies whichever filters have been
 * provided. The controller is responsible for refusing to call it until all
 * three are present.
 */
class InventoryListQuery
{
    public const SORTABLE_COLUMNS = [
        'product_name',
        'number',
        'market_price',
        'low_price',
        'calculated_price',
        'override_price',
        'quantity',
    ];

    /**
     * @param  array{
     *     product_id?: int|null,
     *     set_ids?: list<int>,
     *     conditions?: list<string>,
     *     has_override?: bool,
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

        $sortColumn = match ($sort) {
            'product_name', 'number', 'market_price', 'low_price' => "cards.{$sort}",
            default => "inventory.{$sort}",
        };

        $query->orderBy($sortColumn, $dir);

        // Stable secondary sort so paginated results are deterministic.
        $query->orderBy('cards.product_name', 'asc')
            ->orderBy('cards.number', 'asc')
            ->orderBy('cards.condition', 'asc')
            ->orderBy('inventory.id', 'asc');

        return $query->paginate(
            perPage: $perPage,
            columns: [
                'inventory.id as id',
                'inventory.card_id as card_id',
                'inventory.quantity as quantity',
                'inventory.calculated_price as calculated_price',
                'inventory.override_price as override_price',
                'cards.product_name as product_name',
                'cards.number as number',
                'cards.rarity as rarity',
                'cards.condition as condition',
                'cards.market_price as market_price',
                'cards.low_price as low_price',
            ],
            pageName: 'page',
            page: $page,
        );
    }

    /**
     * @param  array{
     *     product_id?: int|null,
     *     set_ids?: list<int>,
     *     conditions?: list<string>,
     *     has_override?: bool,
     *     in_stock?: bool,
     * }  $filters
     */
    private function base(array $filters): Builder
    {
        $query = Inventory::query()
            ->from('inventory')
            ->join('cards', 'cards.id', '=', 'inventory.card_id')
            ->join('sets', 'sets.id', '=', 'cards.set_id');

        if (! empty($filters['product_id'])) {
            $query->where('sets.product_id', $filters['product_id']);
        }

        if (! empty($filters['set_ids'])) {
            $query->whereIn('cards.set_id', $filters['set_ids']);
        }

        if (! empty($filters['conditions'])) {
            $query->whereIn('cards.condition', $filters['conditions']);
        }

        if (! empty($filters['has_override'])) {
            $query->whereNotNull('inventory.override_price');
        }

        if (! empty($filters['in_stock'])) {
            $query->where('inventory.quantity', '>', 0);
        }

        return $query;
    }

    /**
     * Returns the IDs that match the supplied filters (used by bulk actions
     * when the operator clicks "Select all matching"). Caps at $hardCap and
     * returns null if the matching count exceeds it — the controller maps
     * that to a 413 response.
     *
     * @param  array{
     *     product_id?: int|null,
     *     set_ids?: list<int>,
     *     conditions?: list<string>,
     *     has_override?: bool,
     *     in_stock?: bool,
     * }  $filters
     * @return list<int>|null
     */
    public function matchingIds(array $filters, int $hardCap): ?array
    {
        $count = (clone $this->base($filters))->count();

        if ($count > $hardCap) {
            return null;
        }

        return $this->base($filters)
            ->pluck('inventory.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
