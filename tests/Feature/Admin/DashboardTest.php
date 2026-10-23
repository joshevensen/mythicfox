<?php

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

test('anonymous request to /dashboard redirects to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated request to /dashboard returns 200', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('dashboard passes gameStats as an array to the Inertia page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertInertia(
        fn ($page) => $page
            ->component('Dashboard')
            ->has('gameStats')
    );
});

test('each game stat carries the id of the catalog product with the same name', function () {
    $this->actingAs(User::factory()->create());
    $product = Product::factory()->magic()->create();
    OrderItem::factory()->create(['product_line' => 'Magic', 'total_price' => 500]);

    $this->get(route('dashboard'))->assertInertia(
        fn ($page) => $page
            ->where('gameStats.0.game', 'Magic')
            ->where('gameStats.0.product_id', $product->id)
    );
});

test('a game with no matching catalog product has a null product id', function () {
    $this->actingAs(User::factory()->create());
    OrderItem::factory()->create(['product_line' => 'Unknown Game', 'total_price' => 500]);

    $this->get(route('dashboard'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('gameStats.0.game', 'Unknown Game')
            ->where('gameStats.0.product_id', null)
    );
});

test('the dashboard links each game card to its catalog product page', function () {
    $source = file_get_contents(resource_path('js/pages/Dashboard.vue'));

    expect($source)
        ->toContain("from '@/routes/catalog/products'")
        ->toContain(":is=\"stat.product_id !== null ? Link : 'div'\"")
        ->toContain('productShow(stat.product_id).url')
        ->toContain('game-link-');
});
