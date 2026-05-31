<?php

use App\Models\Deck;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('decks.index'))->assertRedirect(route('login'));
});

test('authenticated visit returns 200 and renders Decks/Index with paginator shape', function () {
    $product = Product::factory()->create(['name' => 'Flesh & Blood TCG']);
    $set = Set::factory()->forProduct($product)->create([
        'name' => 'Blitz Deck: Monarch - Boltyn',
    ]);
    Deck::factory()->state(['set_id' => $set->id])->unopened()->create([
        'product_name' => 'Monarch Boltyn Deck',
        'rarity' => 'Deck',
        'market_price' => 2000,
        'low_price' => 1900,
    ]);

    $this->get(route('decks.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Decks/Index')
                ->has('decks.data', 1)
                ->where('decks.data.0.product_name', 'Monarch Boltyn Deck')
                ->where('decks.data.0.set_name', 'Blitz Deck: Monarch - Boltyn')
                ->where('decks.data.0.condition', 'Unopened')
                ->where('decks.data.0.market_price', 2000)
                ->where('decks.meta.total', 1)
                ->where('decks.meta.per_page', 50)
                ->has('meta.products')
        );
});

test('product filter narrows results', function () {
    $magic = Product::factory()->create(['name' => 'Magic']);
    $fab = Product::factory()->create(['name' => 'Flesh & Blood TCG']);
    $magicSet = Set::factory()->forProduct($magic)->create();
    $fabSet = Set::factory()->forProduct($fab)->create();

    Deck::factory()->state(['set_id' => $magicSet->id])->create(['product_name' => 'Magic Deck']);
    Deck::factory()->state(['set_id' => $fabSet->id])->create(['product_name' => 'FAB Deck']);

    $this->get(route('decks.index', ['product' => $fab->id]))->assertInertia(
        fn ($page) => $page
            ->has('decks.data', 1)
            ->where('decks.data.0.product_name', 'FAB Deck')
    );
});

test('meta.products only includes products that have decks', function () {
    $hasDecks = Product::factory()->create(['name' => 'Has Decks']);
    Product::factory()->create(['name' => 'No Decks']);
    $set = Set::factory()->forProduct($hasDecks)->create();
    Deck::factory()->state(['set_id' => $set->id])->create();

    $this->get(route('decks.index'))->assertInertia(
        fn ($page) => $page
            ->has('meta.products', 1)
            ->where('meta.products.0.label', 'Has Decks')
    );
});

test('empty decks copy points to the global import button instead of Cards', function () {
    $source = file_get_contents(resource_path('js/pages/Decks/Index.vue'));

    expect($source)
        ->toContain('PricingCustomExport from the global import button')
        ->not->toContain('PricingCustomExport on the Cards page');
});
