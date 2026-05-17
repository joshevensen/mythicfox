<?php

use App\Models\User;

test('anonymous request to /dashboard redirects to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated request to /dashboard returns 200', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('greeting uses the first whitespace-delimited token of users.name', function () {
    $this->actingAs(User::factory()->create(['name' => 'Jordan T. Operator']));

    $this->get(route('dashboard'))->assertInertia(
        fn ($page) => $page
            ->component('Dashboard')
            ->where('firstName', 'Jordan')
    );
});

test('greeting falls back to the email local part when users.name is blank', function () {
    $this->actingAs(User::factory()->create([
        'name' => '',
        'email' => 'pickle@example.com',
    ]));

    $this->get(route('dashboard'))->assertInertia(
        fn ($page) => $page->where('firstName', 'pickle')
    );
});

test('all four quick-action tile destinations are present in the rendered Dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();

    $source = file_get_contents(resource_path('js/pages/Dashboard.vue'));

    expect($source)
        ->toContain('addCards().url')
        ->toContain('ordersIndex({ query: { import: 1 } }).url')
        ->toContain('cardsIndex().url')
        ->toContain('decksIndex().url')
        ->toContain('inventoryIndex({ query: { export: 1 } }).url');

    // The Wayfinder route helpers themselves resolve to the actual paths.
    expect(route('add-cards'))->toContain('/add-cards');
    expect(route('orders.index'))->toContain('/orders');
    expect(route('cards.index'))->toContain('/cards');
    expect(route('decks.index'))->toContain('/decks');
    expect(route('inventory.index'))->toContain('/inventory');
});
