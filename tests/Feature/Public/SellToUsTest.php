<?php

use Inertia\Testing\AssertableInertia as Assert;

test('anonymous GET /sell-to-us returns 200 and renders the public SellToUs Inertia page', function () {
    $this->get(route('sell-to-us'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/SellToUs'));
});

test('the sell-to-us page source contains coming-soon copy and email CTA', function () {
    $source = file_get_contents(resource_path('js/pages/public/SellToUs.vue'));

    expect($source)
        ->toContain('Coming Soon')
        ->toContain('Sell Your Collection')
        ->toContain('josh@mythicfoxgames.com');
});
