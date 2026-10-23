<?php

use App\Models\Product;
use App\Models\Set;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('settings.products.index'))->assertRedirect(route('login'));
});

test('authenticated visit returns 200 and exposes products with their inline rule values', function () {
    $product = Product::factory()->magic()->create([
        'base_price' => 50,
        'high_price' => 1500,
        'market_offset' => 5,
        'high_offset' => 25,
    ]);
    Set::factory()->forProduct($product)->create(['name' => 'Alpha Set']);
    Set::factory()->forProduct($product)->create([
        'name' => 'Beta Set',
        'base_price' => 75,
    ]);

    $this->get(route('settings.products.index'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('settings/Products')
            ->has('products', 1)
            ->where('products.0.name', 'Magic')
            ->where('products.0.base_price', 50)
            ->where('products.0.high_price', 1500)
            ->where('products.0.market_offset', 5)
            ->where('products.0.high_offset', 25)
            ->where('products.0.sets_count', 2)
            ->has('products.0.sets', 2)
            ->where('products.0.sets.0.name', 'Alpha Set')
            ->where('products.0.sets.0.overridden', false)
            ->where('products.0.sets.1.name', 'Beta Set')
            ->where('products.0.sets.1.overridden', true)
    );
});

test('updating product pricing rules with valid values persists', function () {
    $product = Product::factory()->magic()->create();

    $this->patch(
        route('settings.products.pricing-rules.update', $product),
        [
            'base_price' => 100,
            'high_price' => 2000,
            'market_offset' => 10,
            'high_offset' => 30,
        ],
    )->assertRedirect();

    expect($product->fresh())
        ->base_price->toBe(100)
        ->high_price->toBe(2000)
        ->market_offset->toBe(10)
        ->high_offset->toBe(30);
});

test('updating product pricing rules rejects base_price greater than high_price with 422', function () {
    $product = Product::factory()->magic()->create();

    $this->patch(
        route('settings.products.pricing-rules.update', $product),
        [
            'base_price' => 5000,
            'high_price' => 1000,
            'market_offset' => 0,
            'high_offset' => 0,
        ],
    )->assertSessionHasErrors('base_price');

    expect($product->fresh()->base_price)->not->toBe(5000);
});

test('updating set pricing rules with all-null fields clears overrides and removes the badge', function () {
    $product = Product::factory()->magic()->create([
        'base_price' => 25,
        'high_price' => 1000,
    ]);
    $set = Set::factory()->forProduct($product)->create([
        'name' => 'Overridden Set',
        'base_price' => 100,
        'high_price' => 2000,
        'market_offset' => 10,
        'high_offset' => 30,
    ]);

    $this->patch(
        route('settings.sets.pricing-rules.update', $set),
        [
            'base_price' => null,
            'high_price' => null,
            'market_offset' => null,
            'high_offset' => null,
        ],
    )->assertRedirect();

    expect($set->fresh())
        ->base_price->toBeNull()
        ->high_price->toBeNull()
        ->market_offset->toBeNull()
        ->high_offset->toBeNull();

    $this->get(route('settings.products.index'))->assertInertia(
        fn ($page) => $page
            ->where('products.0.sets.0.id', $set->id)
            ->where('products.0.sets.0.overridden', false)
    );
});

test('updating set pricing rules rejects base_price greater than effective high_price', function () {
    $product = Product::factory()->magic()->create([
        'base_price' => 25,
        'high_price' => 1000,
    ]);
    $set = Set::factory()->forProduct($product)->create();

    // base_price overrides to 5000 while high_price inherits 1000 → invalid.
    $this->patch(
        route('settings.sets.pricing-rules.update', $set),
        [
            'base_price' => 5000,
            'high_price' => null,
            'market_offset' => null,
            'high_offset' => null,
        ],
    )->assertSessionHasErrors('base_price');
});

test('empty state renders when no products exist', function () {
    Product::query()->delete();

    $this->get(route('settings.products.index'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('settings/Products')
            ->has('products', 0)
    );
});

test('empty state links to the Catalog', function () {
    $source = file_get_contents(resource_path('js/pages/settings/Products.vue'));

    expect($source)
        ->toContain('Go to Catalog')
        ->not->toContain('Go to Cards');
});

test('products page exposes raw name, display_name and display_label', function () {
    Product::factory()->magic()->withDisplayName('MTG')->create();

    $this->get(route('settings.products.index'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('settings/Products')
            ->where('products.0.name', 'Magic')
            ->where('products.0.display_name', 'MTG')
            ->where('products.0.display_label', 'MTG')
    );
});

/**
 * @return array<string, int>
 */
function validProductRules(): array
{
    return [
        'base_price' => 25,
        'high_price' => 1000,
        'market_offset' => 0,
        'high_offset' => 15,
    ];
}

test('updating a product trims and saves display_name and toasts with the label', function () {
    $product = Product::factory()->lorcana()->create();

    $this->patch(
        route('settings.products.pricing-rules.update', $product),
        [...validProductRules(), 'display_name' => '  Lorcana  '],
    )->assertRedirect()->assertSessionHas('toast.message', 'Lorcana pricing rules saved.');

    expect($product->fresh()->display_name)->toBe('Lorcana');
});

test('blank display_name clears it back to null', function (string $blank) {
    $product = Product::factory()->magic()->withDisplayName('Old')->create();

    $this->patch(
        route('settings.products.pricing-rules.update', $product),
        [...validProductRules(), 'display_name' => $blank],
    )->assertRedirect();

    expect($product->fresh()->display_name)->toBeNull();
})->with(['empty' => '', 'whitespace' => '   ']);

test('omitting display_name leaves the stored value unchanged', function () {
    $product = Product::factory()->magic()->withDisplayName('MTG')->create();

    $this->patch(
        route('settings.products.pricing-rules.update', $product),
        validProductRules(),
    )->assertRedirect();

    expect($product->fresh()->display_name)->toBe('MTG');
});

test('display_name longer than 255 characters is rejected', function () {
    $product = Product::factory()->magic()->withDisplayName('MTG')->create();

    $this->patch(
        route('settings.products.pricing-rules.update', $product),
        [...validProductRules(), 'display_name' => str_repeat('a', 256)],
    )->assertSessionHasErrors('display_name');

    expect($product->fresh()->display_name)->toBe('MTG');
});

test('product name cannot be changed through the pricing rules endpoint', function () {
    $product = Product::factory()->magic()->create();

    $this->patch(
        route('settings.products.pricing-rules.update', $product),
        [...validProductRules(), 'name' => 'Hacked'],
    )->assertRedirect();

    expect($product->fresh()->name)->toBe('Magic');
});

test('product modal edits display_name and shows the TCGPlayer name read-only', function () {
    $source = file_get_contents(resource_path('js/components/settings/EditProductRulesModal.vue'));

    expect($source)
        ->toContain('data-test="product-display-name"')
        ->toContain('data-test="product-tcgplayer-name"')
        ->toContain('v-model="form.display_name"')
        ->toContain('display_label')
        ->not->toContain('v-model="form.name"');
});

test('set modal and products page show the product display label', function () {
    $setModal = file_get_contents(resource_path('js/components/settings/EditSetRulesModal.vue'));
    $productsPage = file_get_contents(resource_path('js/pages/settings/Products.vue'));

    expect($setModal)->toContain('product.display_label')->not->toContain('product.name')
        ->and($productsPage)->toContain('product.display_label');
});
