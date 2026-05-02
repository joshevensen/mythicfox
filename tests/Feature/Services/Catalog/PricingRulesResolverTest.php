<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Product;
use App\Services\Catalog\PricingRulesResolver;

test('resolver returns product values when set has all nulls', function () {
    $product = Product::factory()->create([
        'base_price' => 25,
        'high_price' => 1000,
        'market_offset' => 0,
        'high_offset' => 15,
    ]);
    $set = CardSet::factory()->create(['product_id' => $product->id]);

    $rules = PricingRulesResolver::forSet($set);

    expect($rules->basePrice)->toBe(25);
    expect($rules->highPrice)->toBe(1000);
    expect($rules->marketOffset)->toBe(0);
    expect($rules->highOffset)->toBe(15);
});

test('resolver overrides individual fields per-set', function () {
    $product = Product::factory()->create([
        'base_price' => 25,
        'high_price' => 1000,
        'market_offset' => 0,
        'high_offset' => 15,
    ]);

    $set = CardSet::factory()->create([
        'product_id' => $product->id,
        'high_offset' => 50, // override one field
        'base_price' => null,
        'high_price' => null,
        'market_offset' => null,
    ]);

    $rules = PricingRulesResolver::forSet($set);

    expect($rules->highOffset)->toBe(50);
    expect($rules->basePrice)->toBe(25);
    expect($rules->highPrice)->toBe(1000);
    expect($rules->marketOffset)->toBe(0);
});

test('resolver overrides all four fields when all are set', function () {
    $product = Product::factory()->create([
        'base_price' => 25,
        'high_price' => 1000,
        'market_offset' => 0,
        'high_offset' => 15,
    ]);

    $set = CardSet::factory()->create([
        'product_id' => $product->id,
        'base_price' => 100,
        'high_price' => 2000,
        'market_offset' => 25,
        'high_offset' => 50,
    ]);

    $rules = PricingRulesResolver::forSet($set);

    expect($rules->basePrice)->toBe(100);
    expect($rules->highPrice)->toBe(2000);
    expect($rules->marketOffset)->toBe(25);
    expect($rules->highOffset)->toBe(50);
});

test('forCard delegates to forSet', function () {
    $product = Product::factory()->create(['base_price' => 50]);
    $set = CardSet::factory()->create(['product_id' => $product->id, 'base_price' => 75]);
    $card = Card::factory()->create(['set_id' => $set->id]);

    $rules = PricingRulesResolver::forCard($card);

    expect($rules->basePrice)->toBe(75);
});
