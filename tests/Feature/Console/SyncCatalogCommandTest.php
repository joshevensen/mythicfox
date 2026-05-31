<?php

use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\Sync\CatalogSyncSource;
use App\Services\Catalog\Sync\FleshAndBloodSource;
use App\Services\Catalog\Sync\LorcastSource;
use App\Services\Catalog\Sync\ScryfallSource;

beforeEach(function () {
    $this->app->bind(ScryfallSource::class, fn () => new class implements CatalogSyncSource
    {
        public function syncSets(Product $product): int
        {
            return 1;
        }

        public function syncCardsForSet(Set $set): int
        {
            return 3;
        }
    });
});

test('catalog:sync magic runs and outputs summary', function () {
    Product::factory()->create(['name' => 'Magic']);

    $this->artisan('catalog:sync magic')
        ->expectsOutputToContain('Syncing Magic sets')
        ->expectsOutputToContain('Sets upserted: 1')
        ->assertExitCode(0);
});

test('catalog:sync stamps cards_synced_at after sync', function () {
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = Set::factory()->unsynced()->create(['product_id' => $product->id]);

    $this->artisan('catalog:sync magic')->assertExitCode(0);

    expect($set->fresh()->cards_synced_at)->not->toBeNull();
});

test('catalog:sync --force passes force flag to service', function () {
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = Set::factory()->synced()->create(['product_id' => $product->id]);

    $this->artisan('catalog:sync magic --force')
        ->expectsOutputToContain('Sets synced: 1')
        ->assertExitCode(0);
});

test('catalog:sync without game argument runs all games', function () {
    Product::factory()->create(['name' => 'Magic']);
    Product::factory()->create(['name' => 'Lorcana TCG']);
    Product::factory()->create(['name' => 'Flesh & Blood TCG']);

    // Bind all three sources
    foreach ([
        LorcastSource::class,
        FleshAndBloodSource::class,
    ] as $class) {
        $this->app->bind($class, fn () => new class implements CatalogSyncSource
        {
            public function syncSets(Product $product): int
            {
                return 1;
            }

            public function syncCardsForSet(Set $set): int
            {
                return 3;
            }
        });
    }

    $this->artisan('catalog:sync')
        ->expectsOutputToContain('Syncing Magic sets')
        ->expectsOutputToContain('Syncing Lorcana sets')
        ->expectsOutputToContain('Syncing Fab sets')
        ->assertExitCode(0);
});
