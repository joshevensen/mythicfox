<?php

use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('catalog.index'))->assertRedirect(route('login'));
});

test('redirects to the first product ordered by name', function () {
    Product::factory()->create(['name' => 'Zeta']);
    $alpha = Product::factory()->create(['name' => 'Alpha', 'display_name' => 'Omega']);
    Product::factory()->create(['name' => 'Mid']);

    $this->get(route('catalog.index'))
        ->assertRedirect(route('catalog.products.show', $alpha));
});

test('renders the empty state when there are no products', function () {
    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Catalog/Empty'));

    expect(file_get_contents(resource_path('js/pages/Catalog/Empty.vue')))
        ->toContain('CatalogEmptyState');

    expect(file_get_contents(resource_path('js/components/catalog/CatalogEmptyState.vue')))
        ->toContain('data-test="catalog-empty"')
        ->toContain('data-test="catalog-empty-upload"');
});
