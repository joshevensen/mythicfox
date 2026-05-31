<?php

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('cards.index'))->assertRedirect(route('login'));
});

test('authenticated visit returns 200 and renders Catalog/Index with paginator shape', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Welcome to Rathe']);
    Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'Boltyn',
        'number' => 'BOL001',
        'rarity' => 'Rare',
    ]);

    $this->get(route('cards.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Cards/Index')
                ->has('cards.data', 1)
                ->where('cards.data.0.product_name', 'Boltyn')
                ->where('cards.data.0.number', 'BOL001')
                ->where('cards.data.0.set_name', 'Welcome to Rathe')
                ->where('cards.data.0.rarity', 'Rare')
                ->where('cards.data.0.total_qty', 0)
                ->where('cards.meta.per_page', 50)
                ->where('cards.meta.current_page', 1)
                ->where('cards.meta.total', 1)
                ->has('meta.products')
                ->has('meta.products_priced_at')
        );
});

test('parent-row aggregation sums quantity across condition variants', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    $nm = Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'Black Lotus',
        'number' => '1',
        'rarity' => 'M',
    ]);
    $foil = Card::factory()->state(['set_id' => $set->id])->nearMintFoil()->create([
        'product_name' => 'Black Lotus',
        'number' => '1',
        'rarity' => 'M',
    ]);

    Inventory::factory()->state(['card_id' => $nm->id, 'quantity' => 3])->create();
    Inventory::factory()->state(['card_id' => $foil->id, 'quantity' => 2])->create();

    $this->get(route('cards.index'))->assertInertia(
        fn ($page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.total_qty', 5)
    );
});

test('filtering by Product narrows results', function () {
    $magic = Product::factory()->magic()->create();
    $lorcana = Product::factory()->lorcana()->create();
    $magicSet = Set::factory()->forProduct($magic)->create();
    $lorcanaSet = Set::factory()->forProduct($lorcana)->create();

    Card::factory()->state(['set_id' => $magicSet->id])->create(['product_name' => 'Lightning Bolt', 'number' => '1']);
    Card::factory()->state(['set_id' => $lorcanaSet->id])->create(['product_name' => 'Mickey', 'number' => '1']);

    $this->get(route('cards.index', ['product' => $magic->id]))->assertInertia(
        fn ($page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.product_name', 'Lightning Bolt')
    );
});

test('filtering by Set requires a Product to drive option list (chained behavior)', function () {
    $magic = Product::factory()->magic()->create();
    $setA = Set::factory()->forProduct($magic)->create(['name' => 'Alpha']);
    $setB = Set::factory()->forProduct($magic)->create(['name' => 'Beta']);

    Card::factory()->state(['set_id' => $setA->id])->create(['product_name' => 'Card A', 'number' => '1']);
    Card::factory()->state(['set_id' => $setB->id])->create(['product_name' => 'Card B', 'number' => '1']);

    $this->get(route('cards.index', [
        'product' => $magic->id,
        'sets' => (string) $setA->id,
    ]))->assertInertia(
        fn ($page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.product_name', 'Card A')
    );

    // Chained: meta.sets_by_product carries the per-product set list so the
    // page can validate selections client-side when Product changes.
    $this->get(route('cards.index'))->assertInertia(
        fn ($page) => $page->has('meta.sets_by_product.'.$magic->id, 2)
    );
});

test('In stock toggle excludes cards where every condition variant has zero quantity', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    $stocked = Card::factory()->state(['set_id' => $set->id])->create(['product_name' => 'Stocked', 'number' => '1']);
    $unstocked = Card::factory()->state(['set_id' => $set->id])->create(['product_name' => 'Unstocked', 'number' => '2']);

    Inventory::factory()->state(['card_id' => $stocked->id, 'quantity' => 4])->create();
    Inventory::factory()->state(['card_id' => $unstocked->id, 'quantity' => 0])->create();

    $this->get(route('cards.index', ['in_stock' => '1']))->assertInertia(
        fn ($page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.product_name', 'Stocked')
    );
});

test('sort by total_qty desc orders results', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    $low = Card::factory()->state(['set_id' => $set->id])->create(['product_name' => 'Low', 'number' => '1']);
    $high = Card::factory()->state(['set_id' => $set->id])->create(['product_name' => 'High', 'number' => '2']);

    Inventory::factory()->state(['card_id' => $low->id, 'quantity' => 1])->create();
    Inventory::factory()->state(['card_id' => $high->id, 'quantity' => 99])->create();

    $this->get(route('cards.index', ['sort' => 'total_qty', 'dir' => 'desc']))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.product_name', 'High')
            ->where('cards.data.1.product_name', 'Low')
    );
});

test('stale-data indicator data is present in page props with the correct shape', function () {
    Product::factory()->create([
        'name' => 'Magic',
        'priced_at' => Carbon::now()->subDays(2),
    ]);
    Product::factory()->create([
        'name' => 'Lorcana TCG',
        'priced_at' => Carbon::now()->subDays(8),
    ]);
    Product::factory()->create([
        'name' => 'Flesh & Blood TCG',
        'priced_at' => null,
    ]);

    $this->get(route('cards.index'))->assertInertia(
        fn ($page) => $page
            ->has('meta.products_priced_at', 3, fn ($entry) => $entry
                ->has('id')
                ->has('name')
                ->where('priced_at', null)
                ->where('is_stale', true)
            )
    );

    $this->get(route('cards.index'))->assertInertia(
        fn ($page) => $page
            ->where('meta.products_priced_at.0.name', 'Flesh & Blood TCG')
            ->where('meta.products_priced_at.0.is_stale', true)
            ->where('meta.products_priced_at.1.name', 'Lorcana TCG')
            ->where('meta.products_priced_at.1.is_stale', true)
            ->where('meta.products_priced_at.2.name', 'Magic')
            ->where('meta.products_priced_at.2.is_stale', false)
    );
});

test('expand-row variants are eager-loaded into props keyed by parent row key', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    $nm = Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'Boltyn',
        'number' => 'BOL001',
        'rarity' => 'Rare',
        'tcgplayer_id' => 4941474,
    ]);
    $foil = Card::factory()->state(['set_id' => $set->id])->nearMintFoil()->create([
        'product_name' => 'Boltyn',
        'number' => 'BOL001',
        'rarity' => 'Rare',
        'tcgplayer_id' => 4941566,
    ]);

    Inventory::factory()->state(['card_id' => $nm->id, 'quantity' => 2])->create();
    Inventory::factory()->state(['card_id' => $foil->id, 'quantity' => 1])->create();

    $expectedKey = sprintf('%d|Boltyn|BOL001', $set->id);

    $this->get(route('cards.index'))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.key', $expectedKey)
            ->has('variants.'.$expectedKey, 2)
            ->where('variants.'.$expectedKey.'.0.condition', 'Near Mint')
            ->where('variants.'.$expectedKey.'.0.quantity', 2)
            ->where('variants.'.$expectedKey.'.1.condition', 'Near Mint Foil')
            ->where('variants.'.$expectedKey.'.1.quantity', 1)
    );
});
