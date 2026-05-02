<?php

use App\Models\Product;
use Illuminate\Database\QueryException;

test('factory creates a product with default pricing rules', function () {
    $product = Product::factory()->create();

    expect($product->base_price)->toBe(25);
    expect($product->high_price)->toBe(1000);
    expect($product->market_offset)->toBe(0);
    expect($product->high_offset)->toBe(15);
    expect($product->priced_at)->toBeNull();
});

test('name uniqueness is enforced', function () {
    Product::factory()->create(['name' => 'Magic']);

    expect(fn () => Product::factory()->create(['name' => 'Magic']))
        ->toThrow(QueryException::class);
});
