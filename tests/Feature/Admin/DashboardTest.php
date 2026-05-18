<?php

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
