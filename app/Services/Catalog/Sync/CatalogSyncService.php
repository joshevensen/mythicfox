<?php

namespace App\Services\Catalog\Sync;

use App\Models\Product;
use App\Models\Set;
use Illuminate\Support\Carbon;

class CatalogSyncService
{
    /** @var array<string, class-string<CatalogSyncSource>> */
    private const SOURCES = [
        'magic' => ScryfallSource::class,
        'lorcana' => LorcastSource::class,
        'fab' => FleshAndBloodSource::class,
    ];

    /** @var array<string, string> game slug → product name */
    private const PRODUCT_NAMES = [
        'magic' => 'Magic',
        'lorcana' => 'Lorcana TCG',
        'fab' => 'Flesh & Blood TCG',
    ];

    public function sync(string $game, bool $force = false): SyncResult
    {
        $sourceClass = self::SOURCES[$game] ?? throw new \InvalidArgumentException("Unknown game: {$game}");
        $productName = self::PRODUCT_NAMES[$game];

        $product = Product::where('name', $productName)->firstOrFail();
        $source = app($sourceClass);

        $setsUpserted = $source->syncSets($product);

        $sets = $product->sets()
            ->when(! $force, fn ($q) => $q->whereNull('cards_synced_at'))
            ->get();

        $cardsSynced = 0;
        $setsSynced = 0;

        foreach ($sets as $set) {
            try {
                $cardsSynced += $source->syncCardsForSet($set);
                $set->update(['cards_synced_at' => Carbon::now()]);
                $setsSynced++;
            } catch (\Throwable) {
                // One failed set should not abort the run
            }
        }

        return new SyncResult(
            setsUpserted: $setsUpserted,
            cardsSynced: $cardsSynced,
            setsSynced: $setsSynced,
        );
    }

    /** @return array<string> */
    public static function games(): array
    {
        return array_keys(self::SOURCES);
    }
}
