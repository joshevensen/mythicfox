<?php

use Inertia\Testing\AssertableInertia as Assert;

test('anonymous GET /sell-to-us returns 200 and renders the public SellToUs Inertia page', function () {
    $this->get(route('sell-to-us'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/SellToUs'));
});

test('the sell-to-us page exposes the contact email via Inertia props', function () {
    config()->set('brand.contact_email', 'hello@example.com');

    $this->get(route('sell-to-us'))
        ->assertInertia(
            fn (Assert $page) => $page->where('contactEmail', 'hello@example.com')
        );
});

test('the sell-to-us page source contains coming-soon copy and uses the contactEmail prop', function () {
    $source = file_get_contents(resource_path('js/pages/public/SellToUs.vue'));

    expect($source)
        ->toContain('Coming Soon')
        ->toContain('Sell Your Collection')
        ->toContain('contactEmail')
        ->not->toContain('josh@mythicfoxgames.com');
});
