<?php

namespace App\Catalog\Queries;

use App\Models\Set;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated set rows for the catalog product page.
 *
 * One row per set in a product, with a card count and the last card-sync time.
 * Sets that have never been synced sort last in both directions when ordering
 * by cards_synced_at.
 */
class BrowseSetsQuery
{
    public const SORTABLE_COLUMNS = [
        'name',
        'cards_count',
        'cards_synced_at',
    ];

    /**
     * @param  array{
     *     name?: string|null,
     *     sort?: string,
     *     dir?: 'asc'|'desc',
     *     per_page?: int,
     *     page?: int,
     * }  $filters
     */
    public function paginate(int $productId, array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 50;
        $page = $filters['page'] ?? 1;
        $sort = $filters['sort'] ?? 'name';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $name = $filters['name'] ?? null;

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'name';
        }

        $query = Set::query()
            ->where('product_id', $productId)
            ->select(['id', 'product_id', 'name', 'cards_synced_at'])
            ->withCount('cards');

        if (is_string($name) && $name !== '') {
            $query->whereLike('name', '%'.addcslashes($name, '%_\\').'%');
        }

        if ($sort === 'cards_synced_at') {
            $query->orderByRaw('cards_synced_at IS NULL')
                ->orderBy('cards_synced_at', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $query->orderBy('name')->orderBy('id');

        return $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page,
        );
    }
}
