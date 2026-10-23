<?php

/**
 * Serves the catalog card tables: every card in a product, or the cards in one set.
 *
 * @context Inertia controller for the catalog drill-down. Both actions render Catalog/Cards through one
 *          builder, with server-side pagination and rarity filtering driven by BrowseCardsQuery.
 *
 * @gotchas
 * - Variants are eager-loaded per card on every page load (one query per visible card row via BrowseCardsQuery::variantsFor).
 * - Per-page options are constrained to PER_PAGE_OPTIONS to prevent abuse.
 * - Each row's market_price is computed in SQL by BrowseCardsQuery (LP → NM; non-foil → foil → etched) so it can be sorted server-side.
 * - Rarity options are product-wide (cached by RarityOptionsQuery), so the set view can offer a rarity with no cards in that set.
 * - The set route uses scoped bindings, so a set from another product 404s before reaching this controller.
 *
 * @dependencies
 * - BrowseCardsQuery — handles paginator and variant lookups
 * - RarityOptionsQuery — cached rarity filter options per product
 * - CatalogProductPayload — product label and pricing staleness
 */

namespace App\Http\Controllers\Catalog;

use App\Catalog\CatalogProductPayload;
use App\Catalog\Queries\BrowseCardsQuery;
use App\Catalog\Queries\RarityOptionsQuery;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogCardsController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    public function __construct(
        private readonly BrowseCardsQuery $browse,
        private readonly RarityOptionsQuery $rarityOptions,
    ) {}

    public function product(Request $request, Product $product): Response
    {
        return $this->render($request, $product, null);
    }

    public function set(Request $request, Product $product, Set $set): Response
    {
        return $this->render($request, $product, $set);
    }

    private function render(Request $request, Product $product, ?Set $set): Response
    {
        $paginator = $this->browse->paginate([
            'product_id' => $product->id,
            'set_ids' => $set === null ? [] : [$set->id],
            'rarities' => $this->csvStrings($request->query('rarities')),
            'sort' => (string) $request->query('sort', 'name'),
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => $this->resolvePerPage($request),
            'page' => max(1, (int) $request->query('page', 1)),
        ]);

        $rows = [];
        $variants = [];

        foreach ($paginator->items() as $row) {
            $key = (string) $row->id;

            $rows[] = [
                'key' => $key,
                'id' => (int) $row->id,
                'set_id' => (int) $row->set_id,
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'number' => (string) $row->number,
                'set_name' => (string) $row->set_name,
                'rarity' => (string) $row->rarity,
                'market_price' => $row->market_price === null ? null : (int) $row->market_price,
            ];

            $variants[$key] = $this->browse->variantsFor((int) $row->id);
        }

        $label = $product->display_label;

        return Inertia::render('Catalog/Cards', [
            'product' => CatalogProductPayload::for($product),
            'set' => $set === null ? null : ['id' => (int) $set->id, 'name' => (string) $set->name],
            'heading' => $label.' › '.($set === null ? 'All cards' : $set->name),
            'cards' => [
                'data' => $rows,
                'meta' => [
                    'total' => $paginator->total(),
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
            'variants' => $variants,
            'meta' => [
                'rarities' => $this->rarityOptions->byProduct()[(string) $product->id] ?? [],
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function csvStrings(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $v) => trim($v))
            ->filter(fn (string $v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function resolvePerPage(Request $request): int
    {
        $raw = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return in_array($raw, self::PER_PAGE_OPTIONS, true) ? $raw : self::DEFAULT_PER_PAGE;
    }
}
