<?php

use App\Catalog\Queries\RarityOptionsQuery;
use App\Models\Card;
use Illuminate\Support\Facades\Cache;

test('catalog:clear clears the cached rarity options', function () {
    Card::factory()->create();
    Cache::forever(RarityOptionsQuery::CACHE_KEY, ['stale' => []]);

    $this->artisan('catalog:clear --force')->assertSuccessful();

    expect(Cache::has(RarityOptionsQuery::CACHE_KEY))->toBeFalse();
});

test('account:reset clears the cached rarity options', function () {
    Card::factory()->create();
    Cache::forever(RarityOptionsQuery::CACHE_KEY, ['stale' => []]);

    $this->artisan('account:reset')->assertSuccessful();

    expect(Cache::has(RarityOptionsQuery::CACHE_KEY))->toBeFalse();
});
