<?php

use App\Models\SellerStats;
use Illuminate\Support\Carbon;
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

test('the buyers-say section is hidden when no seller_stats row exists', function () {
    $this->get(route('home'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('showBuyersSay', false)
                ->where('sellerStats', null)
        );
});

test('the buyers-say section is hidden when scraped_at is older than 14 days', function () {
    SellerStats::factory()->stale()->create([
        'rating' => 4.8,
        'review_count' => 200,
    ]);

    $this->get(route('home'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('showBuyersSay', false)
                ->where('sellerStats', null)
        );
});

test('the buyers-say section renders rating and review count when fresh', function () {
    SellerStats::factory()->fresh()->create([
        'rating' => 4.9,
        'review_count' => 312,
        'feedback' => [],
    ]);

    $this->get(route('home'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('showBuyersSay', true)
                ->where('sellerStats.rating', 4.9)
                ->where('sellerStats.review_count', 312)
                ->where('sellerStats.feedback', [])
        );
});

test('the buyers-say section trims feedback to at most 3 quotes', function () {
    SellerStats::factory()->fresh()->withFeedback()->create([
        'feedback' => array_fill(0, 5, [
            'text' => 'Great seller.',
            'rating' => 5,
            'author' => 'someone',
            'date' => '2026-04-22',
        ]),
    ]);

    $this->get(route('home'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('showBuyersSay', true)
                ->has('sellerStats.feedback', 3)
        );
});

test('the freshness boundary uses scraped_at, not last_attempt_at', function () {
    SellerStats::factory()->create([
        'rating' => 4.5,
        'review_count' => 100,
        'scraped_at' => Carbon::now()->subDays(15),
        'last_attempt_at' => Carbon::now(),
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('showBuyersSay', false));
});

test('the homepage source renders the static hero, about, and feature copy verbatim', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain('We buy and')
        ->toContain('TCG Cards')
        ->toContain('Mythic Fox Games is your trusted source for buying and')
        ->toContain('Browse Inventory →')
        ->toContain('Great Prices')
        ->toContain('Trusted & Secure')
        ->toContain('Fast & Reliable')
        ->toContain('Built for Collectors')
        ->toContain('Collector Focused')
        ->toContain('Fair & Honest Deals');
});

test('the homepage emits an Organization JSON-LD block', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain("'@type': 'Organization'")
        ->toContain('application/ld+json');
});

test('the homepage feature icons use PrimeIcons', function () {
    $source = file_get_contents(resource_path('js/pages/public/Home.vue'));

    expect($source)
        ->toContain('pi pi-tag')
        ->toContain('pi pi-shield')
        ->toContain('pi pi-truck')
        ->toContain('pi pi-star');
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
