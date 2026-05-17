<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Jobs\ImportOrdersJob;
use App\Models\Order;
use App\Services\Orders\OrderQueryFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    private const SORTABLE = [
        'tcgplayer_order_number',
        'order_date',
        'buyer_name',
        'item_count',
        'total_amount',
        'tcgplayer_status',
    ];

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->query('date_window') === null) {
            return redirect()->to($request->fullUrlWithQuery([
                'date_window' => OrderQueryFilters::DEFAULT_DATE_WINDOW,
            ]));
        }

        return Inertia::render('Orders/Index', [
            'orders' => $this->loadOrders($request),
            'meta' => [
                'statuses' => $this->statusOptions(),
                'date_windows' => OrderQueryFilters::DATE_WINDOWS,
                'import_in_flight' => Cache::has(ImportOrdersJob::IN_FLIGHT_CACHE_KEY),
                'import_last_result' => Cache::get(ImportOrdersJob::LAST_RESULT_CACHE_KEY),
            ],
        ]);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function loadOrders(Request $request): array
    {
        $perPage = $this->resolvePerPage($request);
        $page = max(1, (int) $request->query('page', 1));

        [$sortColumn, $sortDir] = $this->resolveSort($request);

        $query = OrderQueryFilters::apply(Order::query(), $request);
        $query->orderBy($sortColumn, $sortDir);

        $total = (clone $query)->count();
        $rows = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $data = $rows->map(fn (Order $order) => [
            'id' => $order->id,
            'tcgplayer_order_number' => $order->tcgplayer_order_number,
            'tcgplayer_status' => $order->tcgplayer_status,
            'order_date' => $order->order_date?->toDateString(),
            'buyer_name' => $order->buyer_name,
            'item_count' => $order->item_count,
            'total_amount' => $order->total_amount,
            'tracking_number' => $order->tracking_number,
        ])->all();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return Order::query()
            ->select('tcgplayer_status')
            ->distinct()
            ->orderBy('tcgplayer_status')
            ->pluck('tcgplayer_status')
            ->filter(fn (?string $s) => $s !== null && $s !== '')
            ->map(fn (string $s) => ['value' => $s, 'label' => $s])
            ->values()
            ->all();
    }

    private function resolvePerPage(Request $request): int
    {
        $raw = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return in_array($raw, self::PER_PAGE_OPTIONS, true) ? $raw : self::DEFAULT_PER_PAGE;
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function resolveSort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'order_date');
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'order_date';
        }

        return [$sort, $dir];
    }
}
