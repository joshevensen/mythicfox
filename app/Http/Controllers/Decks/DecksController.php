<?php

namespace App\Http\Controllers\Decks;

use App\Http\Controllers\Controller;
use App\Models\Deck;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DecksController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    private const SORTABLE = ['product_name', 'set_name', 'rarity', 'condition', 'market_price'];

    public function index(Request $request): Response
    {
        [$perPage, $page, $sort, $dir] = $this->resolvePaging($request);

        $productId = $this->intOrNull($request->query('product'));
        $setIds = $this->csvIds($request->query('sets'));

        $query = Deck::query()
            ->from('decks')
            ->join('sets', 'decks.set_id', '=', 'sets.id')
            ->select([
                'decks.id',
                'decks.set_id',
                'decks.tcgplayer_id',
                'decks.product_name',
                'decks.rarity',
                'decks.condition',
                'decks.market_price',
                'decks.low_price',
                'sets.name as set_name',
                'sets.product_id',
            ]);

        if ($productId !== null) {
            $query->where('sets.product_id', $productId);
        }

        if ($setIds !== []) {
            $query->whereIn('decks.set_id', $setIds);
        }

        $this->applySort($query, $sort, $dir);

        $paginator = $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page,
        );

        $rows = collect($paginator->items())->map(fn ($row) => [
            'id' => (int) $row->id,
            'set_id' => (int) $row->set_id,
            'product_id' => (int) $row->product_id,
            'tcgplayer_id' => (int) $row->tcgplayer_id,
            'product_name' => (string) $row->product_name,
            'set_name' => (string) $row->set_name,
            'rarity' => (string) $row->rarity,
            'condition' => (string) $row->condition,
            'market_price' => $row->market_price !== null ? (int) $row->market_price : null,
            'low_price' => $row->low_price !== null ? (int) $row->low_price : null,
        ])->all();

        return Inertia::render('Decks/Index', [
            'decks' => [
                'data' => $rows,
                'meta' => [
                    'total' => $paginator->total(),
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
            'meta' => [
                'products' => $this->productOptions(),
                'sets_by_product' => $this->setsByProduct(),
            ],
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->whereIn('id', function ($sub) {
                $sub->from('sets')
                    ->select('product_id')
                    ->whereIn('id', function ($inner) {
                        $inner->from('decks')->select('set_id');
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $p) => [
                'value' => (string) $p->id,
                'label' => $p->name,
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function setsByProduct(): array
    {
        $sets = Set::query()
            ->whereIn('id', function ($sub) {
                $sub->from('decks')->select('set_id');
            })
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

    private function applySort(Builder $query, string $sort, string $dir): void
    {
        if ($sort === 'set_name') {
            $query->orderBy('sets.name', $dir);
        } else {
            $query->orderBy('decks.'.$sort, $dir);
        }

        $query->orderBy('decks.product_name', 'asc')
            ->orderBy('decks.condition', 'asc');
    }

    /**
     * @return array{0: int, 1: int, 2: string, 3: 'asc'|'desc'}
     */
    private function resolvePaging(Request $request): array
    {
        $perPageRaw = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        $perPage = in_array($perPageRaw, self::PER_PAGE_OPTIONS, true)
            ? $perPageRaw
            : self::DEFAULT_PER_PAGE;
        $page = max(1, (int) $request->query('page', 1));

        $sort = (string) $request->query('sort', 'product_name');
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'product_name';
        }

        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return [$perPage, $page, $sort, $dir];
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
}
