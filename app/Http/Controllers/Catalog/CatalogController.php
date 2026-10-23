<?php

/**
 * Serves the /catalog index page with paginated canonical cards, per-card printing variants, filter options, and import status props.
 *
 * @context Inertia controller for the operator-facing catalog browser. Renders Catalog/Index with
 *          server-side pagination and filtering driven by BrowseCardsQuery. Pricing staleness is
 *          surfaced per-product so operators know when a TCGPlayer import is overdue.
 *
 * @gotchas
 * - Variants are eager-loaded per card on every page load (one query per visible card row via BrowseCardsQuery::variantsFor).
 * - Per-page options are constrained to PER_PAGE_OPTIONS to prevent abuse.
 * - Each row's market_price is computed in SQL by BrowseCardsQuery (LP → NM; non-foil → foil → etched) so it can be sorted server-side.
 * - Rarity filter options are the distinct non-empty cards.rarity values per product, alphabetical,
 *   cached by RarityOptionsQuery and cleared when an import or sync finishes.
 *
 * @dependencies
 * - BrowseCardsQuery — handles paginator and variant lookups
 * - RarityOptionsQuery — cached rarity filter options per product
 * - ImportPricingCustomExportJob — cache keys checked for in-flight/last-result status
 */

namespace App\Http\Controllers\Catalog;

use App\Catalog\Queries\BrowseCardsQuery;
use App\Catalog\Queries\RarityOptionsQuery;
use App\Http\Controllers\Controller;
use App\Jobs\ImportPricingCustomExportJob;
use App\Models\Card;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    private const STALE_DAYS = 3;

    public function __construct(
        private readonly BrowseCardsQuery $browse,
        private readonly RarityOptionsQuery $rarityOptions,
    ) {}

    public function index(Request $request): Response
    {
        [$paginator, $rows, $variants] = $this->loadRows($request);

        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'priced_at']);

        $productOptions = $products->map(fn (Product $p) => [
            'value' => (string) $p->id,
            'label' => $p->name,
        ])->all();

        $setsByProduct = $this->setsByProduct();
        $raritiesByProduct = $this->rarityOptions->byProduct();
        $stale = $this->stalenessSummary($products);

        return Inertia::render('Catalog/Index', [
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
                'products' => $productOptions,
                'sets_by_product' => $setsByProduct,
                'rarities_by_product' => $raritiesByProduct,
                'products_priced_at' => $stale,
                'has_any_cards' => $this->hasAnyCards(),
                'import_in_flight' => Cache::has(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY),
                'import_last_result' => Cache::get(ImportPricingCustomExportJob::LAST_RESULT_CACHE_KEY),
                'upload_error' => $request->session()->get('catalog_upload_error'),
            ],
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array<int, array<string, mixed>>, 2: array<string, array<int, array<string, mixed>>>}
     */
    private function loadRows(Request $request): array
    {
        $filters = [
            'product_id' => $this->intOrNull($request->query('product')),
            'set_ids' => $this->csvIds($request->query('sets')),
            'rarities' => $this->csvStrings($request->query('rarities')),
            'sort' => (string) $request->query('sort', 'name'),
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => $this->resolvePerPage($request),
            'page' => max(1, (int) $request->query('page', 1)),
        ];

        $paginator = $this->browse->paginate($filters);

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

        return [$paginator, $rows, $variants];
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function setsByProduct(): array
    {
        $sets = Set::query()
            ->orderBy('product_id')
            ->orderBy('name')
            ->get(['id', 'product_id', 'name']);

        $grouped = [];

        foreach ($sets as $set) {
            $key = (string) $set->product_id;
            $grouped[$key] ??= [];
            $grouped[$key][] = [
                'value' => (string) $set->id,
                'label' => $set->name,
            ];
        }

        return $grouped;
    }

    /**
     * @return array<int, array{id: int, name: string, priced_at: string|null, is_stale: bool}>
     */
    private function stalenessSummary(Collection $products): array
    {
        $threshold = Carbon::now()->subDays(self::STALE_DAYS);

        return $products->map(function (Product $p) use ($threshold) {
            $isStale = $p->priced_at === null || $p->priced_at->lt($threshold);

            return [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'priced_at' => $p->priced_at?->toIso8601String(),
                'is_stale' => $isStale,
            ];
        })->all();
    }

    private function hasAnyCards(): bool
    {
        return Card::query()->exists();
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @return list<int>
     */
    private function csvIds(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $v) => (int) trim($v))
            ->filter(fn (int $v) => $v > 0)
            ->values()
            ->all();
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
