<?php

use App\Models\Card;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guest is redirected to login', function () {
    auth()->logout();
    $product = Product::factory()->magic()->create();

    $this->get(route('catalog.products.show', $product))->assertRedirect(route('login'));
});

test('renders the product\'s sets with card counts', function () {
    $product = Product::factory()->magic()->withDisplayName('MTG')->create();
    $other = Product::factory()->lorcana()->create();
    $setA = Set::factory()->forProduct($product)->synced()->create(['name' => 'Alpha']);
    $setB = Set::factory()->forProduct($product)->unsynced()->create(['name' => 'Beta']);
    $otherSet = Set::factory()->forProduct($other)->create(['name' => 'Gamma']);

    foreach (['1', '2', '3'] as $number) {
        Card::factory()->create(['set_id' => $setA->id, 'number' => $number]);
    }
    Card::factory()->create(['set_id' => $setB->id, 'number' => '1']);
    Card::factory()->create(['set_id' => $otherSet->id, 'number' => '1']);

    $this->get(route('catalog.products.show', $product))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Catalog/Sets')
                ->where('product.id', $product->id)
                ->where('product.label', 'MTG')
                ->has('sets.data', 2)
                ->where('sets.data.0.id', $setA->id)
                ->where('sets.data.0.name', 'Alpha')
                ->where('sets.data.0.cards_count', 3)
                ->where('sets.data.0.cards_synced_at', fn ($v) => is_string($v))
                ->where('sets.data.1.name', 'Beta')
                ->where('sets.data.1.cards_count', 1)
                ->where('sets.data.1.cards_synced_at', null)
                ->where('sets.meta.total', 2)
                ->where('sets.meta.current_page', 1)
                ->where('sets.meta.per_page', 50)
        );
});

test('sorts sets by name, card count and last synced (nulls last)', function () {
    $product = Product::factory()->magic()->create();
    $old = Set::factory()->forProduct($product)->create(['name' => 'Bravo', 'cards_synced_at' => Carbon::now()->subDays(5)]);
    $new = Set::factory()->forProduct($product)->create(['name' => 'Alpha', 'cards_synced_at' => Carbon::now()->subDay()]);
    $never = Set::factory()->forProduct($product)->unsynced()->create(['name' => 'Charlie']);

    Card::factory()->create(['set_id' => $old->id, 'number' => '1']);
    Card::factory()->create(['set_id' => $old->id, 'number' => '2']);
    Card::factory()->create(['set_id' => $new->id, 'number' => '1']);

    $names = function (array $query) use ($product): array {
        $response = $this->get(route('catalog.products.show', [$product, ...$query]));

        return collect($response->inertiaProps('sets.data'))->pluck('name')->all();
    };

    expect($names(['sort' => 'name', 'dir' => 'asc']))->toBe(['Alpha', 'Bravo', 'Charlie'])
        ->and($names(['sort' => 'name', 'dir' => 'desc']))->toBe(['Charlie', 'Bravo', 'Alpha'])
        ->and($names(['sort' => 'cards_count', 'dir' => 'desc']))->toBe(['Bravo', 'Alpha', 'Charlie'])
        ->and($names(['sort' => 'cards_count', 'dir' => 'asc']))->toBe(['Charlie', 'Alpha', 'Bravo'])
        ->and($names(['sort' => 'cards_synced_at', 'dir' => 'asc']))->toBe(['Bravo', 'Alpha', 'Charlie'])
        ->and($names(['sort' => 'cards_synced_at', 'dir' => 'desc']))->toBe(['Alpha', 'Bravo', 'Charlie'])
        ->and($names(['sort' => 'bogus', 'dir' => 'asc']))->toBe(['Alpha', 'Bravo', 'Charlie']);

    expect($never->cards_synced_at)->toBeNull();
});

test('filters sets by name case-insensitively and escapes wildcards', function () {
    $product = Product::factory()->fleshAndBlood()->create();
    Set::factory()->forProduct($product)->create(['name' => 'Welcome to Rathe']);
    Set::factory()->forProduct($product)->create(['name' => 'Arcane Rising']);
    Set::factory()->forProduct($product)->create(['name' => '100% Promo']);

    $this->get(route('catalog.products.show', [$product, 'name' => 'rath']))->assertInertia(
        fn ($page) => $page
            ->has('sets.data', 1)
            ->where('sets.data.0.name', 'Welcome to Rathe')
    );

    $this->get(route('catalog.products.show', [$product, 'name' => '%']))->assertInertia(
        fn ($page) => $page
            ->has('sets.data', 1)
            ->where('sets.data.0.name', '100% Promo')
    );
});

test('paginates sets', function () {
    $product = Product::factory()->magic()->create();

    foreach (range(1, 30) as $i) {
        Set::factory()->forProduct($product)->create(['name' => sprintf('Set %02d', $i)]);
    }

    $this->get(route('catalog.products.show', [$product, 'per_page' => 25]))->assertInertia(
        fn ($page) => $page
            ->has('sets.data', 25)
            ->where('sets.meta.per_page', 25)
            ->where('sets.meta.total', 30)
    );

    $this->get(route('catalog.products.show', [$product, 'per_page' => 25, 'page' => 2]))->assertInertia(
        fn ($page) => $page
            ->has('sets.data', 5)
            ->where('sets.meta.current_page', 2)
            ->where('sets.data.0.name', 'Set 26')
    );

    $this->get(route('catalog.products.show', [$product, 'per_page' => 7]))->assertInertia(
        fn ($page) => $page
            ->has('sets.data', 30)
            ->where('sets.meta.per_page', 50)
    );
});

test('unknown product returns 404', function () {
    $this->get('/catalog/999999')->assertNotFound();
    $this->get('/catalog/abc')->assertNotFound();
});

test('exposes staleness for the current product only', function () {
    $fresh = Product::factory()->create(['name' => 'Fresh', 'priced_at' => Carbon::now()->subDays(2)]);
    $old = Product::factory()->create(['name' => 'Old', 'priced_at' => Carbon::now()->subDays(8)]);
    $never = Product::factory()->create(['name' => 'Never', 'priced_at' => null]);

    $this->get(route('catalog.products.show', $fresh))->assertInertia(
        fn ($page) => $page
            ->where('product.is_stale', false)
            ->where('product.priced_at', fn ($v) => is_string($v))
            ->missing('meta.products_priced_at')
    );

    $this->get(route('catalog.products.show', $old))->assertInertia(
        fn ($page) => $page->where('product.is_stale', true)->where('product.label', 'Old')
    );

    $this->get(route('catalog.products.show', $never))->assertInertia(
        fn ($page) => $page->where('product.is_stale', true)->where('product.priced_at', null)
    );
});

test('sets page source declares the table, filter and links', function () {
    $sets = file_get_contents(resource_path('js/pages/Catalog/Sets.vue'));
    $header = file_get_contents(resource_path('js/components/catalog/CatalogPageHeader.vue'));

    expect($sets)
        ->toContain("kind: 'text'")
        ->toContain("key: 'name'")
        ->toContain("key: 'cards_count'")
        ->toContain("key: 'cards_synced_at'")
        ->toContain("label: 'Cards'")
        ->toContain("label: 'Last synced'")
        ->toContain('data-test="catalog-all-cards"')
        ->toContain('useTableState')
        ->toContain('CatalogPageHeader')
        ->toContain('CatalogEmptyState');

    expect($header)
        ->toContain('data-test="catalog-staleness"')
        ->toContain('MfErrorBanner')
        ->toContain('global_imports');
});

test('a product with no sets shows a product-specific empty message', function () {
    expect(file_get_contents(resource_path('js/pages/Catalog/Sets.vue')))
        ->toContain('No sets for ${product.label} yet.');
});
