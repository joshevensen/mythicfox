<?php

namespace App\Catalog;

use Illuminate\Http\Request;

/**
 * Sort, direction and pagination query params shared by the catalog tables.
 *
 * Per-page values are constrained to PER_PAGE_OPTIONS to prevent abuse; anything
 * else falls back to DEFAULT_PER_PAGE. Sort columns are validated by each query class.
 */
final class CatalogTableParams
{
    public const DEFAULT_PER_PAGE = 50;

    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    /**
     * @return array{sort: string, dir: 'asc'|'desc', per_page: int, page: int}
     */
    public static function fromRequest(Request $request): array
    {
        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return [
            'sort' => (string) $request->query('sort', 'name'),
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE,
            'page' => max(1, (int) $request->query('page', 1)),
        ];
    }
}
