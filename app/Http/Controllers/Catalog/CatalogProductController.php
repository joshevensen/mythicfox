<?php

/**
 * Serves the /catalog/{product} page: a sortable, paginated table of the product's sets.
 *
 * @context Inertia controller for the catalog drill-down. Renders Catalog/Sets for one product with
 *          server-side sorting, name filtering and pagination driven by BrowseSetsQuery.
 *
 * @gotchas
 * - Sort, direction and per-page parsing is shared via CatalogTableParams (per-page constrained to its PER_PAGE_OPTIONS).
 * - Unknown sort columns fall back to name inside BrowseSetsQuery.
 *
 * @dependencies
 * - BrowseSetsQuery — set paginator with card counts
 * - CatalogProductPayload — product label and pricing staleness
 */

namespace App\Http\Controllers\Catalog;

use App\Catalog\CatalogProductPayload;
use App\Catalog\CatalogTableParams;
use App\Catalog\Queries\BrowseSetsQuery;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogProductController extends Controller
{
    public function __construct(private readonly BrowseSetsQuery $browse) {}

    public function show(Request $request, Product $product): Response
    {
        $name = $request->query('name');

        $paginator = $this->browse->paginate($product->id, [
            'name' => is_string($name) ? trim($name) : null,
            ...CatalogTableParams::fromRequest($request),
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
}
