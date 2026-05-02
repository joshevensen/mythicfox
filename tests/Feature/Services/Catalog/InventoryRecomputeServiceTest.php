<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\Catalog\InventoryRecomputeService;
use Illuminate\Support\Carbon;

test('recompute fills calculated_price using product rules from the example table', function () {
    $product = Product::factory()->create([
        'base_price' => 25,
        'high_price' => 1000,
        'market_offset' => 0,
        'high_offset' => 15,
    ]);
    $set = CardSet::factory()->create(['product_id' => $product->id]);

    $cases = [
        ['market' => 10, 'low' => 5, 'expected' => 25],
        ['market' => 300, 'low' => 250, 'expected' => 300],
        ['market' => 300, 'low' => 350, 'expected' => 350],
        ['market' => 1200, 'low' => 1050, 'expected' => 1035],
        ['market' => 1200, 'low' => null, 'expected' => 1185],
    ];

    foreach ($cases as $i => $c) {
        $card = Card::factory()->create([
            'set_id' => $set->id,
            'tcgplayer_id' => 9_000_000 + $i,
            'market_price' => $c['market'],
            'low_price' => $c['low'],
        ]);
        Inventory::factory()->create(['card_id' => $card->id, 'quantity' => 1]);
    }

    $result = (new InventoryRecomputeService)->recompute();

    expect($result->rowsProcessed)->toBe(5);
    expect($result->rowsWithResult)->toBe(5);

    foreach ($cases as $i => $c) {
        $card = Card::where('tcgplayer_id', 9_000_000 + $i)->firstOrFail();
        expect($card->inventory->refresh()->calculated_price)->toBe($c['expected']);
    }
});

test('set-level overrides shadow product fields per-row', function () {
    $product = Product::factory()->create(['base_price' => 25, 'high_price' => 1000, 'market_offset' => 0, 'high_offset' => 15]);
    $productSet = CardSet::factory()->create(['product_id' => $product->id]);
    $overrideSet = CardSet::factory()->create(['product_id' => $product->id, 'base_price' => 200]);

    $cardA = Card::factory()->create(['set_id' => $productSet->id, 'market_price' => 50, 'low_price' => 50]);
    $cardB = Card::factory()->create(['set_id' => $overrideSet->id, 'market_price' => 50, 'low_price' => 50]);
    Inventory::factory()->create(['card_id' => $cardA->id]);
    Inventory::factory()->create(['card_id' => $cardB->id]);

    (new InventoryRecomputeService)->recompute();

    expect($cardA->inventory->refresh()->calculated_price)->toBe(50);
    expect($cardB->inventory->refresh()->calculated_price)->toBe(200);
});

test('calculated_price becomes null when both market and low are null', function () {
    $product = Product::factory()->create();
    $set = CardSet::factory()->create(['product_id' => $product->id]);
    $card = Card::factory()->create(['set_id' => $set->id, 'market_price' => null, 'low_price' => null]);
    Inventory::factory()->create(['card_id' => $card->id, 'calculated_price' => 999]);

    $result = (new InventoryRecomputeService)->recompute();

    expect($result->rowsNullResult)->toBe(1);
    expect($card->inventory->refresh()->calculated_price)->toBeNull();
});

test('recompute does not touch override_price', function () {
    $product = Product::factory()->create();
    $set = CardSet::factory()->create(['product_id' => $product->id]);
    $card = Card::factory()->create(['set_id' => $set->id, 'market_price' => 500, 'low_price' => 400]);
    $inventory = Inventory::factory()->create([
        'card_id' => $card->id,
        'override_price' => 9999,
        'last_exported_price' => 1234,
    ]);

    (new InventoryRecomputeService)->recompute();

    $inventory->refresh();
    expect($inventory->override_price)->toBe(9999);
    expect($inventory->last_exported_price)->toBe(1234);
    expect($inventory->calculated_price)->toBe(500);
});

test('recompute is idempotent', function () {
    $product = Product::factory()->create();
    $set = CardSet::factory()->create(['product_id' => $product->id]);
    $card = Card::factory()->create(['set_id' => $set->id, 'market_price' => 500, 'low_price' => 400]);
    Inventory::factory()->create(['card_id' => $card->id]);

    (new InventoryRecomputeService)->recompute();
    $first = $card->inventory->refresh()->calculated_price;
    (new InventoryRecomputeService)->recompute();
    $second = $card->inventory->refresh()->calculated_price;

    expect($second)->toBe($first);
});

test('stale checker buckets stale and fresh products', function () {
    $stale = Product::factory()->create(['priced_at' => Carbon::now()->subDays(5)]);
    $fresh = Product::factory()->create(['priced_at' => Carbon::now()->subDays(1)]);
    $missing = Product::factory()->create(['priced_at' => null]);

    foreach ([$stale, $fresh, $missing] as $p) {
        $set = CardSet::factory()->create(['product_id' => $p->id]);
        $card = Card::factory()->create(['set_id' => $set->id]);
        Inventory::factory()->create(['card_id' => $card->id]);
    }

    $service = new InventoryRecomputeService;
    $stalePricing = $service->stalePricing();

    $names = collect($stalePricing)->pluck('product');
    expect($names)->toContain($stale->name);
    expect($names)->toContain($missing->name);
    expect($names)->not->toContain($fresh->name);
});

test('stale checker only includes products with inventory rows', function () {
    Product::factory()->create(['priced_at' => Carbon::now()->subDays(7)]);

    $service = new InventoryRecomputeService;

    expect($service->stalePricing())->toBe([]);
});
