<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('inventory.index'))->assertRedirect(route('login'));
});

test('GET /inventory with no filters renders empty-filters Inertia state', function () {
    Product::factory()->magic()->create();

    $this->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Inventory/Index')
                ->where('meta.filters_complete', false)
                ->has('rows.data', 0)
                ->has('meta.products')
                ->has('meta.sets_by_product')
                ->has('meta.conditions')
        );
});

test('GET /inventory with all required filters returns rows', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create(['name' => 'Welcome to Rathe']);

    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'Boltyn',
        'number' => 'BOL001',
        'rarity' => 'Rare',
    ]);

    Inventory::factory()->state([
        'card_id' => $card->id,
        'quantity' => 4,
    ])->create();

    $this->get(route('inventory.index', [
        'product' => $product->id,
        'sets' => (string) $set->id,
        'conditions' => 'Near Mint',
    ]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Inventory/Index')
                ->where('meta.filters_complete', true)
                ->has('rows.data', 1)
                ->where('rows.data.0.product_name', 'Boltyn')
                ->where('rows.data.0.number', 'BOL001')
                ->where('rows.data.0.condition', 'Near Mint')
                ->where('rows.data.0.quantity', 4)
        );
});

test('partial filters keep the empty-filters state', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();

    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    Inventory::factory()->state(['card_id' => $card->id])->create();

    // Product + Set chosen, but no Condition — still incomplete.
    $this->get(route('inventory.index', [
        'product' => $product->id,
        'sets' => (string) $set->id,
    ]))->assertInertia(
        fn ($page) => $page
            ->where('meta.filters_complete', false)
            ->has('rows.data', 0)
    );
});

test('In stock toggle excludes zero-qty rows', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();

    $stocked = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'Stocked']);
    $unstocked = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'Unstocked']);

    Inventory::factory()->state(['card_id' => $stocked->id, 'quantity' => 3])->create();
    Inventory::factory()->state(['card_id' => $unstocked->id, 'quantity' => 0])->create();

    // Default (in_stock off): both rows visible.
    $this->get(route('inventory.index', [
        'product' => $product->id,
        'sets' => (string) $set->id,
        'conditions' => 'Near Mint',
    ]))->assertInertia(
        fn ($page) => $page->has('rows.data', 2)
    );

    $this->get(route('inventory.index', [
        'product' => $product->id,
        'sets' => (string) $set->id,
        'conditions' => 'Near Mint',
        'in_stock' => '1',
    ]))->assertInertia(
        fn ($page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.product_name', 'Stocked')
    );
});

test('Has override toggle filters to rows with non-null override_price', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();

    $a = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'A']);
    $b = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'B']);

    Inventory::factory()->state(['card_id' => $a->id, 'quantity' => 1])->withOverride(500)->create();
    Inventory::factory()->state(['card_id' => $b->id, 'quantity' => 1])->create();

    $this->get(route('inventory.index', [
        'product' => $product->id,
        'sets' => (string) $set->id,
        'conditions' => 'Near Mint',
        'has_override' => '1',
    ]))->assertInertia(
        fn ($page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.product_name', 'A')
    );
});

test('sort by quantity desc orders results', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();

    $low = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'Low']);
    $high = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'High']);

    Inventory::factory()->state(['card_id' => $low->id, 'quantity' => 1])->create();
    Inventory::factory()->state(['card_id' => $high->id, 'quantity' => 99])->create();

    $this->get(route('inventory.index', [
        'product' => $product->id,
        'sets' => (string) $set->id,
        'conditions' => 'Near Mint',
        'sort' => 'quantity',
        'dir' => 'desc',
    ]))->assertInertia(
        fn ($page) => $page
            ->where('rows.data.0.product_name', 'High')
            ->where('rows.data.1.product_name', 'Low')
    );
});

test('override_count meta reflects the live count of rows with overrides', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();

    $a = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'A']);
    $b = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'B']);
    $c = Card::factory()->state(['set_id' => $set->id])->nearMint()->create(['product_name' => 'C']);

    Inventory::factory()->state(['card_id' => $a->id])->withOverride(500)->create();
    Inventory::factory()->state(['card_id' => $b->id])->withOverride(700)->create();
    Inventory::factory()->state(['card_id' => $c->id])->create();

    $this->get(route('inventory.index'))
        ->assertInertia(fn ($page) => $page->where('meta.override_count', 2));
});
