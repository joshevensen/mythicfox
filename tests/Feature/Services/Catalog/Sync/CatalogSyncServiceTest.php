<?php

use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\Sync\CatalogSyncService;
use App\Services\Catalog\Sync\CatalogSyncSource;
use App\Services\Catalog\Sync\ScryfallSource;
use Illuminate\Support\Carbon;

function fakeSource(int $setsToUpsert = 1, int $cardsPerSet = 5): CatalogSyncSource
{
    return new class($setsToUpsert, $cardsPerSet) implements CatalogSyncSource
    {
        public function __construct(
            private int $setsToUpsert,
            private int $cardsPerSet,
        ) {}

        public function syncSets(Product $product): int
        {
            return $this->setsToUpsert;
        }

        public function syncCardsForSet(Set $set): int
        {
            return $this->cardsPerSet;
        }
    };
}

beforeEach(function () {
    // Bind a fake source for magic so we can control what the service does
    $this->app->bind(ScryfallSource::class, fn () => fakeSource());
});

test('sync stamps cards_synced_at on each set after card sync', function () {
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = Set::factory()->unsynced()->create(['product_id' => $product->id]);

    (new CatalogSyncService)->sync('magic');

    expect($set->fresh()->cards_synced_at)->not->toBeNull();
});

test('sync skips sets that already have cards_synced_at', function () {
    $product = Product::factory()->create(['name' => 'Magic']);
    $syncedAt = Carbon::now()->subDay();
    $set = Set::factory()->synced()->create([
        'product_id' => $product->id,
        'cards_synced_at' => $syncedAt,
    ]);

    $result = (new CatalogSyncService)->sync('magic');

    expect($result->setsSynced)->toBe(0);
    expect($result->cardsSynced)->toBe(0);
    // Timestamp unchanged
    expect($set->fresh()->cards_synced_at->timestamp)->toBe($syncedAt->timestamp);
});

test('sync with --force re-syncs sets that already have cards_synced_at', function () {
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = Set::factory()->synced()->create(['product_id' => $product->id]);

    $result = (new CatalogSyncService)->sync('magic', force: true);

    expect($result->setsSynced)->toBe(1);
    expect($result->cardsSynced)->toBe(5);
});

test('sync returns correct result counts', function () {
    $product = Product::factory()->create(['name' => 'Magic']);
    Set::factory()->unsynced()->count(3)->create(['product_id' => $product->id]);

    $this->app->bind(ScryfallSource::class, fn () => fakeSource(setsToUpsert: 3, cardsPerSet: 10));

    $result = (new CatalogSyncService)->sync('magic');

    expect($result->setsUpserted)->toBe(3);
    expect($result->setsSynced)->toBe(3);
    expect($result->cardsSynced)->toBe(30);
});

test('sync continues if one set card sync throws', function () {
    $product = Product::factory()->create(['name' => 'Magic']);
    Set::factory()->unsynced()->count(2)->create(['product_id' => $product->id]);

    $callCount = 0;
    $throwingSource = new class implements CatalogSyncSource
    {
        public int $calls = 0;

        public function syncSets(Product $product): int
        {
            return 2;
        }

        public function syncCardsForSet(Set $set): int
        {
            $this->calls++;
            if ($this->calls === 1) {
                throw new RuntimeException('API failure');
            }

            return 5;
        }
    };

    $this->app->bind(ScryfallSource::class, fn () => $throwingSource);

    $result = (new CatalogSyncService)->sync('magic');

    expect($result->setsSynced)->toBe(1);
    expect($result->cardsSynced)->toBe(5);
});

test('throws for unknown game', function () {
    expect(fn () => (new CatalogSyncService)->sync('pokemon'))->toThrow(InvalidArgumentException::class);
});
