<?php

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guest is redirected to login', function () {
    auth()->logout();
    $card = Card::factory()->create();
    $product = $card->set->product;

    $this->get(route('catalog.products.cards.show', [$product, $card]))->assertRedirect(route('login'));
});

test('renders the card with its product, set and heading', function () {
    $product = Product::factory()->magic()->withDisplayName('MTG')->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Alpha']);
    $card = Card::factory()->create(['set_id' => $set->id, 'name' => 'Boltyn', 'number' => '12', 'rarity' => 'R']);

    $this->get(route('catalog.products.cards.show', [$product, $card]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Catalog/Card')
                ->where('product.id', $product->id)
                ->where('product.label', 'MTG')
                ->has('product.is_stale')
                ->where('set.id', $set->id)
                ->where('set.name', 'Alpha')
                ->where('card.id', $card->id)
                ->where('card.name', 'Boltyn')
                ->where('card.number', '12')
                ->where('card.rarity', 'R')
                ->where('heading', 'MTG › Alpha › Boltyn')
        );
});

test('a card from another product returns 404', function () {
    $product = Product::factory()->magic()->create();
    $other = Product::factory()->lorcana()->create();
    $otherSet = Set::factory()->forProduct($other)->create();
    $card = Card::factory()->create(['set_id' => $otherSet->id]);

    $this->get(route('catalog.products.cards.show', [$product, $card]))->assertNotFound();
    $this->get("/catalog/{$product->id}/cards/999999")->assertNotFound();
});

test('non-numeric ids return 404', function () {
    $card = Card::factory()->create();
    $product = $card->set->product;

    $this->get("/catalog/{$product->id}/cards/abc")->assertNotFound();
    $this->get("/catalog/abc/cards/{$card->id}")->assertNotFound();
});

test('lists every printing with per-condition prices', function () {
    $card = Card::factory()->create();
    $product = $card->set->product;

    Printing::factory()->nonFoil()->create([
        'card_id' => $card->id,
        'tcgplayer_id' => 111,
        'nm_market_price' => 500,
        'nm_low_price' => 400,
        'lp_market_price' => 300,
        'lp_low_price' => null,
        'mp_market_price' => null,
        'mp_low_price' => 100,
    ]);
    Printing::factory()->foil()->create(['card_id' => $card->id]);
    Printing::factory()->create(['card_id' => Card::factory()]);

    $this->get(route('catalog.products.cards.show', [$product, $card]))
        ->assertInertia(
            fn ($page) => $page
                ->has('printings', 2)
                ->where('printings.0.finish', 'foil')
                ->where('printings.1.finish', 'non-foil')
                ->where('printings.1.tcgplayer_id', 111)
                ->where('printings.1.nm_market_price', 500)
                ->where('printings.1.nm_low_price', 400)
                ->where('printings.1.lp_market_price', 300)
                ->where('printings.1.lp_low_price', null)
                ->where('printings.1.mp_market_price', null)
                ->where('printings.1.mp_low_price', 100)
        );
});

test('selects the image by finish priority', function () {
    $imageFor = function (array $printings): ?string {
        $card = Card::factory()->create();

        foreach ($printings as [$finish, $url]) {
            Printing::factory()->create(['card_id' => $card->id, 'finish' => $finish, 'image_url' => $url]);
        }

        $captured = null;
        $this->get(route('catalog.products.cards.show', [$card->set->product, $card]))
            ->assertInertia(function ($page) use (&$captured) {
                $captured = $page->toArray()['props']['card']['image_url'];

                return $page;
            });

        return $captured;
    };

    expect($imageFor([['etched', 'e.png'], ['foil', 'f.png'], ['non-foil', 'n.png']]))->toBe('n.png')
        ->and($imageFor([['etched', 'e.png'], ['foil', 'f.png']]))->toBe('f.png')
        ->and($imageFor([['promo', 'p.png'], ['etched', 'e.png']]))->toBe('e.png')
        ->and($imageFor([['promo', 'p.png']]))->toBe('p.png')
        ->and($imageFor([['non-foil', null], ['foil', 'f.png']]))->toBe('f.png')
        ->and($imageFor([['non-foil', null], ['foil', null]]))->toBeNull();

    $card = Card::factory()->create();
    $this->get(route('catalog.products.cards.show', [$card->set->product, $card]))
        ->assertInertia(fn ($page) => $page->where('card.image_url', null)->has('printings', 0));
});

test('card page source declares header, image, details and printings', function () {
    $source = file_get_contents(resource_path('js/pages/Catalog/Card.vue'));

    expect($source)
        ->toContain('<Head :title="heading" />')
        ->toContain('CatalogPageHeader')
        ->toContain('productShow(')
        ->toContain('setShow(')
        ->toContain('data-test="catalog-card-image"')
        ->toContain('data-test="catalog-card-image-placeholder"')
        ->toContain('data-test="catalog-card-details"')
        ->toContain('data-test="catalog-card-set-link"')
        ->toContain('data-test="catalog-card-printings"')
        ->toContain('CatalogPrintingsTable');
});

test('card names link to the card page', function () {
    $cards = file_get_contents(resource_path('js/pages/Catalog/Cards.vue'));

    expect($cards)
        ->toContain('cardShow(')
        ->toContain('CatalogPrintingsTable')
        ->toContain('@click.stop')
        ->toContain('@keydown.enter.stop')
        ->not->toContain('@/pages/Catalog/RowExpand.vue')
        ->and(substr_count($cards, ':data-test="`catalog-card-link-${row.id}`"'))->toBe(2);

    expect(file_exists(resource_path('js/pages/Catalog/RowExpand.vue')))->toBeFalse();
});
