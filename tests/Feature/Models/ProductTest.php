<?php

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

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

test('products table has a nullable display_name column', function () {
    expect(Schema::hasColumn('products', 'display_name'))->toBeTrue()
        ->and(Product::factory()->create()->display_name)->toBeNull();
});

test('display_label falls back to name when display_name is null', function () {
    expect(Product::factory()->magic()->create()->display_label)->toBe('Magic');
});

test('display_label returns display_name when set', function () {
    expect(Product::factory()->magic()->withDisplayName('MTG')->create()->display_label)->toBe('MTG');
});

test('display_name is mass-assignable', function () {
    $product = Product::factory()->create();

    $product->update(['display_name' => 'X']);

    expect($product->fresh()->display_name)->toBe('X');
});
