<?php

namespace App\Http\Controllers\Catalog;

use App\Catalog\Queries\BrowseCardsQuery;
use App\Http\Controllers\Controller;
use App\Jobs\ImportPricingCustomExportJob;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\Product;
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

    public function __construct(private readonly BrowseCardsQuery $browse) {}

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
            'in_stock' => $request->query('in_stock') === '1',
            'sort' => (string) $request->query('sort', 'product_name'),
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => $this->resolvePerPage($request),
            'page' => max(1, (int) $request->query('page', 1)),
        ];

        $paginator = $this->browse->paginate($filters);

        $rows = [];
        $variants = [];

        foreach ($paginator->items() as $row) {
            $key = sprintf('%d|%s|%s', $row->set_id, $row->product_name, $row->number);

            $rows[] = [
                'key' => $key,
                'set_id' => (int) $row->set_id,
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'number' => (string) $row->number,
                'set_name' => (string) $row->set_name,
                'rarity' => (string) $row->rarity,
                'total_qty' => (int) $row->total_qty,
            ];

            $variants[$key] = $this->browse->variantsFor(
                (int) $row->set_id,
                (string) $row->product_name,
                (string) $row->number,
            );
        }

        return [$paginator, $rows, $variants];
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function setsByProduct(): array
    {
        $sets = CardSet::query()
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

    private function resolvePerPage(Request $request): int
    {
        $raw = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return in_array($raw, self::PER_PAGE_OPTIONS, true) ? $raw : self::DEFAULT_PER_PAGE;
    }
}
