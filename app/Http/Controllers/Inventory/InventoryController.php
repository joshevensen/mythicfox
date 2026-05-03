<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Inventory\Queries\InventoryListQuery;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public const DEFAULT_PER_PAGE = 50;

    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    private const STALE_DAYS = 3;

    public function __construct(private readonly InventoryListQuery $list) {}

    public function index(Request $request): Response
    {
        $filters = $this->resolveFilters($request);
        $filtersComplete = $this->filtersComplete($filters);

        [$rows, $total, $currentPage, $perPage] = $filtersComplete
            ? $this->loadRows($filters)
            : [[], 0, max(1, (int) $request->query('page', 1)), $this->resolvePerPage($request)];

        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'priced_at']);

        $productOptions = $products->map(fn (Product $p) => [
            'value' => (string) $p->id,
            'label' => $p->name,
        ])->all();

        return Inertia::render('Inventory/Index', [
            'rows' => [
                'data' => $rows,
                'meta' => [
                    'total' => $total,
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                ],
            ],
            'meta' => [
                'filters_complete' => $filtersComplete,
                'products' => $productOptions,
                'sets_by_product' => $this->setsByProduct(),
                'conditions' => $this->conditionOptions(),
                'products_priced_at' => $this->stalenessSummary($products),
                'override_count' => $this->overrideCount(),
            ],
        ]);
    }

    public function update(Request $request, Inventory $inventory): array
    {
        $payload = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'override_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('quantity', $payload)) {
            $inventory->quantity = (int) $payload['quantity'];
        }

        if (array_key_exists('override_price', $payload)) {
            $inventory->override_price = $payload['override_price'] === null
                ? null
                : (int) $payload['override_price'];
        }

        $inventory->save();

        return [
            'inventory' => $this->presentRow($inventory->fresh(['card.set'])),
            'override_count' => $this->overrideCount(),
        ];
    }

    public function destroy(Inventory $inventory): array
    {
        // Soft-only: zero the quantity and clear the override. The row is
        // never hard-deleted per docs/ux/inventory.md#per-row-actions.
        $inventory->quantity = 0;
        $inventory->override_price = null;
        $inventory->save();

        return [
            'inventory' => $this->presentRow($inventory->fresh(['card.set'])),
            'override_count' => $this->overrideCount(),
        ];
    }

    public function bulkClearOverrides(Request $request): array
    {
        return $this->bulkApply($request, function (array $ids) {
            Inventory::query()
                ->whereIn('id', $ids)
                ->update(['override_price' => null]);
        });
    }

    public function bulkMarkOutOfStock(Request $request): array
    {
        return $this->bulkApply($request, function (array $ids) {
            // override_price is intentionally preserved here — distinct from
            // the per-row Remove action, which clears both.
            Inventory::query()
                ->whereIn('id', $ids)
                ->update(['quantity' => 0]);
        });
    }

    private function bulkApply(Request $request, callable $mutate): array
    {
        $hardCap = 1000;

        $payload = $request->validate([
            'ids' => ['nullable', 'array', 'max:'.$hardCap],
            'ids.*' => ['integer', 'min:1'],
            'select_all' => ['nullable', 'boolean'],
        ]);

        $ids = $payload['ids'] ?? [];
        $selectAll = (bool) ($payload['select_all'] ?? false);

        if (count($ids) === 0 && $selectAll) {
            $filters = $this->resolveFilters($request);

            abort_unless(
                $this->filtersComplete($filters),
                422,
                'Bulk select-all requires Product, Set, and Condition filters.',
            );

            $resolved = $this->list->matchingIds($filters, $hardCap);

            abort_if(
                $resolved === null,
                413,
                'More than '.$hardCap.' rows match — narrow the filters before applying a bulk action.',
            );

            $ids = $resolved;
        }

        abort_if(count($ids) === 0, 422, 'No inventory rows selected.');

        $mutate($ids);

        return [
            'updated' => count($ids),
            'override_count' => $this->overrideCount(),
        ];
    }

    /**
     * @return array{
     *     0: list<array<string, mixed>>,
     *     1: int,
     *     2: int,
     *     3: int,
     * }
     */
    private function loadRows(array $filters): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->list->paginate($filters);

        $items = collect($paginator->items())
            ->map(fn ($row) => $this->presentRowRaw($row))
            ->values()
            ->all();

        return [
            $items,
            $paginator->total(),
            $paginator->currentPage(),
            $paginator->perPage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(Inventory $inventory): array
    {
        $card = $inventory->card;
        $set = $card->set;

        return [
            'id' => (int) $inventory->id,
            'card_id' => (int) $inventory->card_id,
            'product_name' => (string) $card->product_name,
            'number' => (string) $card->number,
            'set_id' => (int) $card->set_id,
            'set_name' => (string) $set->name,
            'condition' => (string) $card->condition,
            'rarity' => (string) $card->rarity,
            'market_price' => $card->market_price === null ? null : (int) $card->market_price,
            'low_price' => $card->low_price === null ? null : (int) $card->low_price,
            'calculated_price' => $inventory->calculated_price === null ? null : (int) $inventory->calculated_price,
            'override_price' => $inventory->override_price === null ? null : (int) $inventory->override_price,
            'quantity' => (int) $inventory->quantity,
        ];
    }

    /**
     * Used when iterating paginator->items() — those are stdClass with the
     * select() columns, not full Eloquent models, so we shape directly.
     *
     * @return array<string, mixed>
     */
    private function presentRowRaw(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'card_id' => (int) $row->card_id,
            'product_name' => (string) $row->product_name,
            'number' => (string) $row->number,
            'condition' => (string) $row->condition,
            'rarity' => (string) $row->rarity,
            'market_price' => $row->market_price === null ? null : (int) $row->market_price,
            'low_price' => $row->low_price === null ? null : (int) $row->low_price,
            'calculated_price' => $row->calculated_price === null ? null : (int) $row->calculated_price,
            'override_price' => $row->override_price === null ? null : (int) $row->override_price,
            'quantity' => (int) $row->quantity,
        ];
    }

    /**
     * @return array{
     *     product_id: int|null,
     *     set_ids: list<int>,
     *     conditions: list<string>,
     *     has_override: bool,
     *     in_stock: bool,
     *     sort: string,
     *     dir: 'asc'|'desc',
     *     per_page: int,
     *     page: int,
     * }
     */
    private function resolveFilters(Request $request): array
    {
        // Read from input() so the same filter signature works whether passed
        // via query string (GET /inventory) or JSON body (POST bulk endpoints
        // with select_all=true).
        return [
            'product_id' => $this->intOrNull($request->input('product')),
            'set_ids' => $this->csvIntList($request->input('sets')),
            'conditions' => $this->csvStringList($request->input('conditions')),
            'has_override' => $request->input('has_override') === '1',
            'in_stock' => $request->input('in_stock') === '1',
            'sort' => (string) $request->input('sort', 'product_name'),
            'dir' => $request->input('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => $this->resolvePerPage($request),
            'page' => max(1, (int) $request->input('page', 1)),
        ];
    }

    private function filtersComplete(array $filters): bool
    {
        return $filters['product_id'] !== null
            && count($filters['set_ids']) > 0
            && count($filters['conditions']) > 0;
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
     * Distinct condition strings present in `cards`. Falls back to an empty
     * list pre-seed; the page just renders an empty multi-select in that
     * case.
     *
     * @return list<array{value: string, label: string}>
     */
    private function conditionOptions(): array
    {
        return Card::query()
            ->select('condition')
            ->distinct()
            ->orderBy('condition')
            ->pluck('condition')
            ->filter(fn (?string $c) => $c !== null && $c !== '')
            ->map(fn (string $c) => ['value' => $c, 'label' => $c])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, priced_at: string|null, is_stale: bool}>
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
        })->values()->all();
    }

    private function overrideCount(): int
    {
        return Inventory::query()
            ->whereNotNull('override_price')
            ->count();
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
    private function csvIntList(mixed $value): array
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
    private function csvStringList(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $v) => trim($v))
            ->filter(fn (string $v) => $v !== '')
            ->values()
            ->all();
    }

    private function resolvePerPage(Request $request): int
    {
        $raw = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);

        return in_array($raw, self::PER_PAGE_OPTIONS, true) ? $raw : self::DEFAULT_PER_PAGE;
    }
}
