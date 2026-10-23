<?php

use App\Catalog\Queries\RarityOptionsQuery;
use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('product cards page lists all cards in the product', function () {
    $product = Product::factory()->magic()->create();
    $other = Product::factory()->lorcana()->create();
    $setA = Set::factory()->forProduct($product)->create(['name' => 'Welcome to Rathe']);
    $setB = Set::factory()->forProduct($product)->create(['name' => 'Arcane Nexus']);
    $otherSet = Set::factory()->forProduct($other)->create();

    foreach (['Rare', 'Common', 'Rare', ''] as $i => $rarity) {
        Card::factory()->create(['set_id' => $setA->id, 'name' => 'Card '.$i, 'number' => (string) $i, 'rarity' => $rarity]);
    }
    Card::factory()->create(['set_id' => $setB->id, 'name' => 'Zed', 'number' => '9', 'rarity' => 'Legendary']);
    Card::factory()->create(['set_id' => $otherSet->id, 'name' => 'Mickey', 'number' => '1', 'rarity' => 'Enchanted']);

    $this->get(route('catalog.products.cards.index', $product))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Catalog/Cards')
                ->where('product.id', $product->id)
                ->where('set', null)
                ->where('heading', $product->display_label.' › All cards')
                ->where('cards.meta.total', 5)
                ->where('cards.meta.per_page', 50)
                ->where('cards.meta.current_page', 1)
                ->has('cards.data.0', fn ($row) => $row
                    ->hasAll(['key', 'id', 'set_id', 'product_id', 'name', 'number', 'set_name', 'rarity', 'market_price'])
                    ->etc()
                )
                ->has('meta.rarities', 3)
                ->where('meta.rarities.0', ['value' => 'Common', 'label' => 'Common'])
                ->where('meta.rarities.1', ['value' => 'Legendary', 'label' => 'Legendary'])
                ->where('meta.rarities.2', ['value' => 'Rare', 'label' => 'Rare'])
        );
});

test('set cards page lists only that set\'s cards with a Product › Set heading', function () {
    $product = Product::factory()->magic()->withDisplayName('MTG')->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Alpha']);
    $otherSet = Set::factory()->forProduct($product)->create(['name' => 'Beta']);

    Card::factory()->create(['set_id' => $set->id, 'name' => 'In Set', 'number' => '1']);
    Card::factory()->create(['set_id' => $otherSet->id, 'name' => 'Out Of Set', 'number' => '1']);

    $this->get(route('catalog.products.sets.show', [$product, $set]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Catalog/Cards')
                ->where('set.id', $set->id)
                ->where('set.name', 'Alpha')
                ->where('heading', 'MTG › Alpha')
                ->where('cards.meta.total', 1)
                ->where('cards.data.0.name', 'In Set')
        );
});

test('a set that does not belong to the product returns 404', function () {
    $productA = Product::factory()->magic()->create();
    $productB = Product::factory()->lorcana()->create();
    $setOfB = Set::factory()->forProduct($productB)->create();

    $this->get(route('catalog.products.sets.show', [$productA, $setOfB]))->assertNotFound();
    $this->get("/catalog/{$productA->id}/sets/999999")->assertNotFound();
});

test('card pages expose the current product\'s staleness', function () {
    $fresh = Product::factory()->create(['name' => 'Fresh', 'priced_at' => Carbon::now()->subDays(2)]);
    $old = Product::factory()->create(['name' => 'Old', 'priced_at' => Carbon::now()->subDays(8)]);
    $never = Product::factory()->create(['name' => 'Never', 'priced_at' => null]);
    $oldSet = Set::factory()->forProduct($old)->create();

    $this->get(route('catalog.products.cards.index', $fresh))->assertInertia(
        fn ($page) => $page
            ->where('product.is_stale', false)
            ->where('product.priced_at', fn ($v) => is_string($v))
    );

    $this->get(route('catalog.products.cards.index', $old))->assertInertia(
        fn ($page) => $page->where('product.is_stale', true)->where('product.label', 'Old')
    );

    $this->get(route('catalog.products.sets.show', [$old, $oldSet]))->assertInertia(
        fn ($page) => $page->where('product.is_stale', true)
    );

    $this->get(route('catalog.products.cards.index', $never))->assertInertia(
        fn ($page) => $page->where('product.is_stale', true)->where('product.priced_at', null)
    );
});

test('sort by name orders results', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    Card::factory()->create(['set_id' => $set->id, 'name' => 'Low', 'number' => '1']);
    Card::factory()->create(['set_id' => $set->id, 'name' => 'High', 'number' => '2']);

    $this->get(route('catalog.products.cards.index', [$product, 'sort' => 'name', 'dir' => 'asc']))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.name', 'High')
            ->where('cards.data.1.name', 'Low')
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
    Printing::factory()->withoutPricing()->create([
        'card_id' => $card->id,
        'finish' => 'non-foil',
        'tcgplayer_id' => 4941474,
        'nm_market_price' => 150,
        'nm_low_price' => 120,
        'lp_market_price' => 140,
        'mp_low_price' => 90,
    ]);
    Printing::factory()->foil()->withPricing('nm', 250, 200)->create([
        'card_id' => $card->id,
        'tcgplayer_id' => 4941566,
    ]);

    $expectedKey = (string) $card->id;

    $this->get(route('catalog.products.cards.index', $product))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.key', $expectedKey)
            ->has('variants.'.$expectedKey, 2)
            ->where('variants.'.$expectedKey.'.0.finish', 'foil')
            ->where('variants.'.$expectedKey.'.0.tcgplayer_id', 4941566)
            ->has('variants.'.$expectedKey.'.1', fn ($variant) => $variant
                ->where('finish', 'non-foil')
                ->where('tcgplayer_id', 4941474)
                ->where('nm_market_price', 150)
                ->where('nm_low_price', 120)
                ->where('lp_market_price', 140)
                ->where('lp_low_price', null)
                ->where('mp_market_price', null)
                ->where('mp_low_price', 90)
            )
    );
});

/**
 * Creates a card in a fresh set and returns its catalog row's market_price.
 *
 * @param  callable(Card): void  $withPrintings
 */
function catalogMarketPriceFor(callable $withPrintings): ?int
{
    $card = Card::factory()->create();
    $withPrintings($card);

    $response = test()->get(route('catalog.products.cards.index', $card->set->product_id));
    $row = collect($response->viewData('page')['props']['cards']['data'])->firstWhere('id', $card->id);

    return $row['market_price'];
}

test('market price prefers LP and falls back to NM', function () {
    expect(catalogMarketPriceFor(function (Card $card) {
        Printing::factory()->withoutPricing()->withPricing('lp', 300, null)->withPricing('nm', 500, null)
            ->create(['card_id' => $card->id]);
    }))->toBe(300);

    expect(catalogMarketPriceFor(function (Card $card) {
        Printing::factory()->withoutPricing()->withPricing('nm', 500, null)
            ->create(['card_id' => $card->id]);
    }))->toBe(500);
});

test('market price falls back non-foil → foil → etched', function () {
    expect(catalogMarketPriceFor(function (Card $card) {
        Printing::factory()->withoutPricing()->create(['card_id' => $card->id]);
        Printing::factory()->foil()->withoutPricing()->withPricing('nm', 700, null)->create(['card_id' => $card->id]);
        Printing::factory()->etched()->withoutPricing()->withPricing('lp', 900, null)->create(['card_id' => $card->id]);
    }))->toBe(700);

    expect(catalogMarketPriceFor(function (Card $card) {
        Printing::factory()->withoutPricing()->create(['card_id' => $card->id]);
        Printing::factory()->foil()->withoutPricing()->create(['card_id' => $card->id]);
        Printing::factory()->etched()->withoutPricing()->withPricing('lp', 900, null)->create(['card_id' => $card->id]);
    }))->toBe(900);

    expect(catalogMarketPriceFor(function (Card $card) {
        Printing::factory()->create(['card_id' => $card->id, 'finish' => 'rainbow-foil']);
    }))->toBeNull();
});

test('market price is null when no printing has a price', function () {
    expect(catalogMarketPriceFor(fn (Card $card) => null))->toBeNull();

    expect(catalogMarketPriceFor(function (Card $card) {
        Printing::factory()->withoutPricing()->create(['card_id' => $card->id]);
    }))->toBeNull();
});

test('sort by market price orders asc/desc with nulls last', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    foreach (['Cheap' => 100, 'Pricey' => 300, 'Unpriced' => null] as $name => $price) {
        $card = Card::factory()->create(['set_id' => $set->id, 'name' => $name]);
        $printing = Printing::factory()->withoutPricing();

        if ($price !== null) {
            $printing = $printing->withPricing('lp', $price, null);
        }

        $printing->create(['card_id' => $card->id]);
    }

    $this->get(route('catalog.products.cards.index', [$product, 'sort' => 'market_price', 'dir' => 'asc']))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.market_price', 100)
            ->where('cards.data.1.market_price', 300)
            ->where('cards.data.2.market_price', null)
            ->where('cards.data.2.name', 'Unpriced')
    );

    $this->get(route('catalog.products.cards.index', [$product, 'sort' => 'market_price', 'dir' => 'desc']))->assertInertia(
        fn ($page) => $page
            ->where('cards.data.0.market_price', 300)
            ->where('cards.data.1.market_price', 100)
            ->where('cards.data.2.market_price', null)
            ->where('cards.meta.total', 3)
    );
});

test('card table source keeps rarity, market price and row expand without product or set filters', function () {
    $cards = file_get_contents(resource_path('js/pages/Catalog/Cards.vue'));
    $expand = file_get_contents(resource_path('js/components/catalog/CatalogPrintingsTable.vue'));

    expect($cards)
        ->toContain("key: 'rarities'")
        ->toContain("key: 'market_price'")
        ->toContain("label: 'Market Price'")
        ->toContain('data-test="catalog-card-market-price"')
        ->toContain('CatalogPrintingsTable')
        ->not->toContain('@/pages/Catalog/RowExpand.vue')
        ->toContain('<Head :title="heading" />')
        ->toContain('CatalogPageHeader')
        ->toContain('CatalogEmptyState')
        ->not->toContain("key: 'product'")
        ->not->toContain("key: 'sets'")
        ->not->toContain('pruneDependentFilters');

    expect($expand)
        ->toContain("'NM Market'")
        ->toContain("'NM Low'")
        ->toContain("'LP Market'")
        ->toContain("'LP Low'")
        ->toContain("'MP Market'")
        ->toContain("'MP Low'")
        ->not->toContain('v.market_price')
        ->not->toContain('v.low_price');
});

test('filters by rarity and ignores blank segments', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    foreach (['Common', 'Rare', 'Super Rare'] as $i => $rarity) {
        Card::factory()->create(['set_id' => $set->id, 'number' => (string) $i, 'rarity' => $rarity]);
    }

    foreach ([
        route('catalog.products.cards.index', [$product, 'rarities' => 'Rare,Super Rare']),
        route('catalog.products.sets.show', [$product, $set, 'rarities' => 'Rare,Super Rare']),
    ] as $url) {
        $response = $this->get($url);

        $response->assertInertia(fn ($page) => $page->where('cards.meta.total', 2));

        $rarities = collect($response->inertiaProps('cards.data'))->pluck('rarity')->sort()->values()->all();

        expect($rarities)->toBe(['Rare', 'Super Rare']);
    }

    $this->get(route('catalog.products.cards.index', [$product, 'rarities' => ', ,']))->assertInertia(
        fn ($page) => $page->where('cards.meta.total', 3)
    );
    $this->get(route('catalog.products.sets.show', [$product, $set, 'rarities' => ', ,']))->assertInertia(
        fn ($page) => $page->where('cards.meta.total', 3)
    );
});

test('ignores legacy product and sets query params', function () {
    $product = Product::factory()->magic()->create();
    $other = Product::factory()->lorcana()->create();
    $setA = Set::factory()->forProduct($product)->create();
    $setB = Set::factory()->forProduct($product)->create();
    $otherSet = Set::factory()->forProduct($other)->create();

    Card::factory()->create(['set_id' => $setA->id, 'number' => '1']);
    Card::factory()->create(['set_id' => $setB->id, 'number' => '1']);
    Card::factory()->create(['set_id' => $otherSet->id, 'number' => '1']);

    $url = route('catalog.products.cards.index', $product)
        .'?product='.$other->id.'&sets='.$setA->id;

    $this->get($url)->assertInertia(fn ($page) => $page->where('cards.meta.total', 2));
});

test('rarity options are cached until forgotten', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();
    Card::factory()->create(['set_id' => $set->id, 'number' => '1', 'rarity' => 'Common']);

    $url = route('catalog.products.cards.index', $product);

    $this->get($url)->assertInertia(fn ($page) => $page->has('meta.rarities', 1));

    Card::factory()->create(['set_id' => $set->id, 'number' => '2', 'rarity' => 'Rare']);

    $this->get($url)->assertInertia(fn ($page) => $page->has('meta.rarities', 1));

    RarityOptionsQuery::forget();

    $this->get($url)->assertInertia(fn ($page) => $page->has('meta.rarities', 2));
});
