<?php

namespace App\Services\Catalog;

use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryRecomputeService
{
    public const StaleThresholdDays = 3;

    public const ChunkSize = 500;

    public function recompute(): InventoryRecomputeResult
    {
        $rowsProcessed = 0;
        $rowsWithResult = 0;
        $rowsNullResult = 0;

        Inventory::query()
            ->with('card.set.product')
            ->chunkById(self::ChunkSize, function ($chunk) use (&$rowsProcessed, &$rowsWithResult, &$rowsNullResult) {
                DB::transaction(function () use ($chunk, &$rowsProcessed, &$rowsWithResult, &$rowsNullResult) {
                    foreach ($chunk as $inventory) {
                        $rowsProcessed++;
                        $rules = PricingRulesResolver::forCard($inventory->card);
                        $price = PriceCalculator::calculate(
                            $inventory->card->market_price,
                            $inventory->card->low_price,
                            $rules,
                        );

                        $inventory->calculated_price = $price;
                        $inventory->save();

                        if ($price === null) {
                            $rowsNullResult++;
                        } else {
                            $rowsWithResult++;
                        }
                    }
                });
            });

        return new InventoryRecomputeResult(
            rowsProcessed: $rowsProcessed,
            rowsWithResult: $rowsWithResult,
            rowsNullResult: $rowsNullResult,
            stale: $this->stalePricing(),
        );
    }

    /**
     * @return list<array{product:string, age_days:?int, inventory_rows:int}>
     */
    public function stalePricing(): array
    {
        $threshold = Carbon::now()->subDays(self::StaleThresholdDays);

        $rows = DB::table('products')
            ->join('sets', 'sets.product_id', '=', 'products.id')
            ->join('cards', 'cards.set_id', '=', 'sets.id')
            ->join('inventory', 'inventory.card_id', '=', 'cards.id')
            ->where(function ($q) use ($threshold) {
                $q->whereNull('products.priced_at')->orWhere('products.priced_at', '<', $threshold);
            })
            ->groupBy('products.id', 'products.name', 'products.priced_at')
            ->select([
                'products.id',
                'products.name as product_name',
                'products.priced_at as priced_at',
                DB::raw('count(inventory.id) as inventory_count'),
            ])
            ->get();

        return $rows->map(function ($row) {
            $pricedAt = $row->priced_at ? Carbon::parse($row->priced_at) : null;

            return [
                'product' => $row->product_name,
                'age_days' => $pricedAt?->diffInDays(Carbon::now()),
                'inventory_rows' => (int) $row->inventory_count,
            ];
        })->all();
    }
}
