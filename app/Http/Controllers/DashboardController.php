<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $baseStats = $this->baseStats();
        $topSets = $this->topSets();
        $rarityMix = $this->rarityMix();
        $avgItemsPerOrder = $this->avgItemsPerOrder();

        $gameStats = $baseStats->map(function (OrderItem $item) use ($topSets, $rarityMix, $avgItemsPerOrder) {
            $pl = $item->product_line;
            $cardsSold = (int) $item->cards_sold;
            $totalRevenue = (int) $item->total_revenue;

            return [
                'game' => $pl,
                'total_revenue' => $totalRevenue,
                'cards_sold' => $cardsSold,
                'avg_price_per_card' => $cardsSold > 0 ? (int) round($totalRevenue / $cardsSold) : null,
                'top_sets' => $topSets->get($pl, []),
                'rarity_mix' => $rarityMix->get($pl, []),
                'avg_items_per_order' => isset($avgItemsPerOrder[$pl])
                    ? round((float) $avgItemsPerOrder[$pl]->avg_items, 1)
                    : null,
                'max_items_per_order' => isset($avgItemsPerOrder[$pl])
                    ? (int) $avgItemsPerOrder[$pl]->max_items
                    : null,
            ];
        })->values()->all();

        return Inertia::render('Dashboard', [
            'gameStats' => $gameStats,
        ]);
    }

    /** @return Collection<int, OrderItem> */
    private function baseStats(): Collection
    {
        return OrderItem::query()
            ->select([
                'product_line',
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('SUM(quantity) as cards_sold'),
            ])
            ->whereNotNull('total_price')
            ->groupBy('product_line')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    /** @return Collection<string, array<int, array<string, mixed>>> */
    private function topSets(): Collection
    {
        return OrderItem::query()
            ->select([
                'product_line',
                'set_name',
                DB::raw('SUM(quantity) as cards_sold'),
                DB::raw('SUM(total_price) as revenue'),
            ])
            ->whereNotNull('total_price')
            ->groupBy('product_line', 'set_name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->groupBy('product_line')
            ->map(fn (Collection $sets) => $sets
                ->take(5)
                ->map(fn (OrderItem $s) => [
                    'name' => $s->set_name,
                    'cards_sold' => (int) $s->cards_sold,
                    'revenue' => (int) $s->revenue,
                ])
                ->values()
                ->all()
            );
    }

    /** @return Collection<string, array<int, array<string, mixed>>> */
    private function rarityMix(): Collection
    {
        return OrderItem::query()
            ->select([
                'product_line',
                'rarity',
                DB::raw('SUM(quantity) as cards_sold'),
            ])
            ->groupBy('product_line', 'rarity')
            ->get()
            ->groupBy('product_line')
            ->map(function (Collection $rarities) {
                $total = $rarities->sum('cards_sold');

                return $rarities
                    ->sortByDesc('cards_sold')
                    ->map(fn (OrderItem $r) => [
                        'rarity' => $r->rarity,
                        'cards_sold' => (int) $r->cards_sold,
                        'pct' => $total > 0 ? (int) round($r->cards_sold / $total * 100) : 0,
                    ])
                    ->values()
                    ->all();
            });
    }

    /** @return Collection<string, object{avg_items: string, max_items: string}> */
    private function avgItemsPerOrder(): Collection
    {
        $sub = DB::table('order_items')
            ->select([
                'product_line',
                'order_id',
                DB::raw('SUM(quantity) as order_qty'),
            ])
            ->groupBy('product_line', 'order_id');

        return DB::table(DB::raw("({$sub->toSql()}) as per_order"))
            ->mergeBindings($sub)
            ->select([
                'product_line',
                DB::raw('AVG(order_qty) as avg_items'),
                DB::raw('MAX(order_qty) as max_items'),
            ])
            ->groupBy('product_line')
            ->get()
            ->keyBy('product_line');
    }
}
