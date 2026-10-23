<?php

/**
 * Serves the /catalog/{product} page: a sortable, paginated table of the product's sets.
 *
 * @context Inertia controller for the catalog drill-down. Renders Catalog/Sets for one product with
 *          server-side sorting, name filtering and pagination driven by BrowseSetsQuery.
 *
 * @gotchas
 * - Per-page options are constrained to PER_PAGE_OPTIONS to prevent abuse.
 * - Unknown sort columns fall back to name inside BrowseSetsQuery.
 *
 * @dependencies
 * - BrowseSetsQuery — set paginator with card counts
 * - CatalogProductPayload — product label and pricing staleness
 */

namespace App\Http\Controllers\Catalog;

use App\Catalog\CatalogProductPayload;
use App\Catalog\Queries\BrowseSetsQuery;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogProductController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    public function __construct(private readonly BrowseSetsQuery $browse) {}

    public function show(Request $request, Product $product): Response
    {
        $name = $request->query('name');

        $paginator = $this->browse->paginate($product->id, [
            'name' => is_string($name) ? trim($name) : null,
            'sort' => (string) $request->query('sort', 'name'),
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => $this->resolvePerPage($request),
            'page' => max(1, (int) $request->query('page', 1)),
        ]);

        $rows = collect($paginator->items())
            ->map(fn (Set $set): array => [
                'id' => (int) $set->id,
                'name' => (string) $set->name,
                'cards_count' => (int) $set->cards_count,
                'cards_synced_at' => $set->cards_synced_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Catalog/Sets', [
            'product' => CatalogProductPayload::for($product),
            'sets' => [
                'data' => $rows,
                'meta' => [
                    'total' => $paginator->total(),
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    private function resolvePerPage(Request $request): int
    {
        $raw = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return in_array($raw, self::PER_PAGE_OPTIONS, true) ? $raw : self::DEFAULT_PER_PAGE;
    }
}
