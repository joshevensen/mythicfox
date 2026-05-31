<?php

use Inertia\Testing\AssertableInertia as Assert;

test('anonymous GET / returns 200 and renders the public Home Inertia page', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/Home'));
});

test('the homepage exposes the TCGPlayer storefront URL via Inertia props', function () {
    config()->set('services.tcgplayer.storefront_url', 'https://www.tcgplayer.com/sellers/Mythic-Fox-Games/abc123');

    $this->get(route('home'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('tcgplayerStorefrontUrl', 'https://www.tcgplayer.com/sellers/Mythic-Fox-Games/abc123')
        );
});

test('the homepage source renders the static hero, about, and feature copy verbatim', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain('We buy and')
        ->toContain('TCG Cards')
        ->toContain('Whether you\'re looking to buy singles or sell your')
        ->toContain('Browse Inventory →')
        ->toContain('Sell Your Collection →')
        ->toContain('Great Prices')
        ->toContain('Trusted & Secure')
        ->toContain('Fast & Reliable')
        ->toContain('Built for Collectors')
        ->toContain('Collector Focused')
        ->toContain('Fair & Honest Deals')
        ->toContain('Sell Your Collection')
        ->toContain('sellToUs')
        ->toContain('Admin');
});

test('the homepage emits an Organization JSON-LD block', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain("'@type': 'Organization'")
        ->toContain('application/ld+json');
});

test('the homepage feature icons use Lucide', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain('lucide-vue-next')
        ->toContain('Tag')
        ->toContain('Shield')
        ->toContain('Truck')
        ->toContain('Star');
});

test('the homepage Vue component receives storefront URL via props (not hardcoded)', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain('tcgplayerStorefrontUrl')
        ->not->toContain('https://www.tcgplayer.com/sellers');
});

test('the controller applies the staleness rule server-side, not in the view', function () {
    $source = file_get_contents(app_path('Http/Controllers/PublicHomepageController.php'));

    expect($source)
        ->toContain('subDays(14)')
        ->toContain('showBuyersSay');
});
