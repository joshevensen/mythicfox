<?php

use App\Models\CardSet;
use App\Models\Product;
use Illuminate\Database\QueryException;

test('factory creates a set attached to a product with null pricing overrides', function () {
    $set = CardSet::factory()->create();

    expect($set->product)->toBeInstanceOf(Product::class);
    expect($set->base_price)->toBeNull();
    expect($set->high_price)->toBeNull();
    expect($set->market_offset)->toBeNull();
    expect($set->high_offset)->toBeNull();
});

test('product hasMany sets relation resolves', function () {
    $product = Product::factory()->create();
    CardSet::factory()->count(3)->create(['product_id' => $product->id]);

    expect($product->sets()->count())->toBe(3);
});

test('unique constraint on product_id + name is enforced', function () {
    $product = Product::factory()->create();
    CardSet::factory()->create(['product_id' => $product->id, 'name' => 'Welcome to Rathe']);

    expect(fn () => CardSet::factory()->create([
        'product_id' => $product->id,
        'name' => 'Welcome to Rathe',
    ]))->toThrow(QueryException::class);
});

test('same set name is allowed under different products', function () {
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    CardSet::factory()->create(['product_id' => $a->id, 'name' => 'Core']);
    $second = CardSet::factory()->create(['product_id' => $b->id, 'name' => 'Core']);

    expect($second->id)->not->toBeNull();
});
