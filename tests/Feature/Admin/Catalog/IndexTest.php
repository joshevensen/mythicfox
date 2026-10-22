<?php

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('catalog.index'))->assertRedirect(route('login'));
});

test('authenticated visit returns 200 and renders Catalog/Index with paginator shape', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Welcome to Rathe']);
    Card::factory()->create([
        'set_id' => $set->id,
        'name' => 'Boltyn',
        'number' => 'BOL001',
        'rarity' => 'Rare',
    ]);

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Catalog/Index')
                ->has('cards.data', 1)
                ->where('cards.data.0.name', 'Boltyn')
                ->where('cards.data.0.number', 'BOL001')
                ->where('cards.data.0.set_name', 'Welcome to Rathe')
                ->where('cards.data.0.rarity', 'Rare')
                ->where('cards.meta.per_page', 50)
                ->where('cards.meta.current_page', 1)
                ->where('cards.meta.total', 1)
                ->has('meta.products')
                ->has('meta.products_priced_at')
        );
});

test('filtering by Product narrows results', function () {
    $magic = Product::factory()->magic()->create();
    $lorcana = Product::factory()->lorcana()->create();
    $magicSet = Set::factory()->forProduct($magic)->create();
    $lorcanaSet = Set::factory()->forProduct($lorcana)->create();

    Card::factory()->create(['set_id' => $magicSet->id, 'name' => 'Lightning Bolt', 'number' => '1']);
    Card::factory()->create(['set_id' => $lorcanaSet->id, 'name' => 'Mickey', 'number' => '1']);

    $this->get(route('catalog.index', ['product' => $magic->id]))->assertInertia(
        fn ($page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.name', 'Lightning Bolt')
    );
});

test('filtering by Set requires a Product to drive option list', function () {
    $magic = Product::factory()->magic()->create();
    $setA = Set::factory()->forProduct($magic)->create(['name' => 'Alpha']);
    $setB = Set::factory()->forProduct($magic)->create(['name' => 'Beta']);

    Card::factory()->create(['set_id' => $setA->id, 'name' => 'Card A', 'number' => '1']);
    Card::factory()->create(['set_id' => $setB->id, 'name' => 'Card B', 'number' => '1']);

    $this->get(route('catalog.index', [
        'product' => $magic->id,
        'sets' => (string) $setA->id,
    ]))->assertInertia(
        fn ($page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.name', 'Card A')
    );

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page->has('meta.sets_by_product.'.$magic->id, 2)
    );
});

test('sort by name orders results', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    Card::factory()->create(['set_id' => $set->id, 'name' => 'Low', 'number' => '1']);
    Card::factory()->create(['set_id' => $set->id, 'name' => 'High', 'number' => '2']);

    $this->get(route('catalog.index', ['sort' => 'name', 'dir' => 'asc']))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.name', 'High')
            ->where('cards.data.1.name', 'Low')
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

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->has('meta.products_priced_at', 3, fn ($entry) => $entry
                ->has('id')
                ->has('name')
                ->where('priced_at', null)
                ->where('is_stale', true)
            )
    );

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->where('meta.products_priced_at.0.name', 'Flesh & Blood TCG')
            ->where('meta.products_priced_at.0.is_stale', true)
            ->where('meta.products_priced_at.1.name', 'Lorcana TCG')
            ->where('meta.products_priced_at.1.is_stale', true)
            ->where('meta.products_priced_at.2.name', 'Magic')
            ->where('meta.products_priced_at.2.is_stale', false)
    );
});

test('expand-row variants are eager-loaded into props keyed by card row key', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    $card = Card::factory()->create([
        'set_id' => $set->id,
        'name' => 'Boltyn',
        'number' => 'BOL001',
        'rarity' => 'Rare',
    ]);
    Printing::factory()->create([
        'card_id' => $card->id,
        'finish' => 'non-foil',
        'tcgplayer_id' => 4941474,
        'market_price' => 150,
        'low_price' => 120,
    ]);
    Printing::factory()->foil()->create([
        'card_id' => $card->id,
        'tcgplayer_id' => 4941566,
        'market_price' => 250,
        'low_price' => 200,
    ]);

    $expectedKey = (string) $card->id;

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.key', $expectedKey)
            ->has('variants.'.$expectedKey, 2)
            ->where('variants.'.$expectedKey.'.0.finish', 'foil')
            ->where('variants.'.$expectedKey.'.0.tcgplayer_id', 4941566)
            ->where('variants.'.$expectedKey.'.1.finish', 'non-foil')
            ->where('variants.'.$expectedKey.'.1.market_price', 150)
    );
});

test('catalog page title and header read Catalog', function () {
    $source = file_get_contents(resource_path('js/pages/Catalog/Index.vue'));

    expect($source)
        ->toContain('<Head title="Catalog" />')
        ->toContain('<MfPageHeader title="Catalog"')
        ->not->toContain('title="Cards"');
});

test('rarities_by_product lists distinct non-empty rarities alphabetically', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    foreach (['Rare', 'Common', 'Rare', 'Legendary', ''] as $i => $rarity) {
        Card::factory()->create(['set_id' => $set->id, 'number' => (string) $i, 'rarity' => $rarity]);
    }

    $key = 'meta.rarities_by_product.'.$product->id;

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->has($key, 3)
            ->where($key.'.0', ['value' => 'Common', 'label' => 'Common'])
            ->where($key.'.1', ['value' => 'Legendary', 'label' => 'Legendary'])
            ->where($key.'.2', ['value' => 'Rare', 'label' => 'Rare'])
    );
});

test('rarities_by_product is scoped per product', function () {
    $magic = Product::factory()->magic()->create();
    $lorcana = Product::factory()->lorcana()->create();
    $empty = Product::factory()->create(['name' => 'Empty Product']);
    $magicSet = Set::factory()->forProduct($magic)->create();
    $lorcanaSet = Set::factory()->forProduct($lorcana)->create();

    Card::factory()->create(['set_id' => $magicSet->id, 'number' => '1', 'rarity' => 'Mythic']);
    Card::factory()->create(['set_id' => $lorcanaSet->id, 'number' => '1', 'rarity' => 'Enchanted']);

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->has('meta.rarities_by_product.'.$magic->id, 1)
            ->where('meta.rarities_by_product.'.$magic->id.'.0.value', 'Mythic')
            ->has('meta.rarities_by_product.'.$lorcana->id, 1)
            ->where('meta.rarities_by_product.'.$lorcana->id.'.0.value', 'Enchanted')
            ->missing('meta.rarities_by_product.'.$empty->id)
    );
});

test('filtering by rarities narrows results', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    foreach (['Common', 'Rare', 'Super Rare'] as $i => $rarity) {
        Card::factory()->create(['set_id' => $set->id, 'number' => (string) $i, 'rarity' => $rarity]);
    }

    $response = $this->get(route('catalog.index', ['product' => $product->id, 'rarities' => 'Rare,Super Rare']));

    $response->assertInertia(fn ($page) => $page->where('cards.meta.total', 2));

    $rarities = collect($response->inertiaProps('cards.data'))->pluck('rarity')->sort()->values()->all();

    expect($rarities)->toBe(['Rare', 'Super Rare']);
});

test('rarity filter combines with set filter', function () {
    $product = Product::factory()->magic()->create();
    $setA = Set::factory()->forProduct($product)->create(['name' => 'Set A']);
    $setB = Set::factory()->forProduct($product)->create(['name' => 'Set B']);

    foreach ([$setA, $setB] as $set) {
        Card::factory()->create(['set_id' => $set->id, 'name' => $set->name.' Rare', 'number' => '1', 'rarity' => 'Rare']);
        Card::factory()->create(['set_id' => $set->id, 'name' => $set->name.' Common', 'number' => '2', 'rarity' => 'Common']);
    }

    $this->get(route('catalog.index', [
        'product' => $product->id,
        'sets' => (string) $setA->id,
        'rarities' => 'Rare',
    ]))->assertInertia(
        fn ($page) => $page
            ->where('cards.meta.total', 1)
            ->where('cards.data.0.name', 'Set A Rare')
    );
});

test('blank rarities segments are ignored', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    Card::factory()->create(['set_id' => $set->id, 'number' => '1', 'rarity' => 'Common']);
    Card::factory()->create(['set_id' => $set->id, 'number' => '2', 'rarity' => 'Rare']);

    $this->get(route('catalog.index', ['rarities' => ', ,']))->assertInertia(
        fn ($page) => $page->where('cards.meta.total', 2)
    );
});
