<?php

use App\Jobs\RefreshSellerStats;
use App\Models\SellerStats;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('settings'))->assertRedirect(route('login'));
});

test('authenticated visit renders the seller stats card with values from a singleton', function () {
    SellerStats::factory()->fresh()->withFeedback()->create([
        'rating' => 4.9,
        'review_count' => 312,
    ]);

    $this->get(route('settings'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Settings')
            ->where('sellerStats.rating', 4.9)
            ->where('sellerStats.review_count', 312)
            ->where('sellerStats.feedback_count', 3)
            ->where('sellerStats.status.key', 'healthy')
    );
});

test('status derivation: healthy when fresh and no failures', function () {
    SellerStats::factory()->create([
        'scraped_at' => Carbon::now()->subDays(2),
        'last_attempt_at' => Carbon::now()->subDays(2),
        'consecutive_failures' => 0,
    ]);

    $this->get(route('settings'))->assertInertia(
        fn ($page) => $page->where('sellerStats.status.key', 'healthy'),
    );
});

test('status derivation: failed when 3+ consecutive failures', function () {
    SellerStats::factory()->create([
        'scraped_at' => Carbon::now()->subDays(2),
        'last_attempt_at' => Carbon::now(),
        'consecutive_failures' => 4,
        'last_error' => 'Selector not found',
    ]);

    $this->get(route('settings'))->assertInertia(
        fn ($page) => $page
            ->where('sellerStats.status.key', 'failed')
            ->where('sellerStats.last_error', 'Selector not found')
    );
});

test('status derivation: stale when scraped_at is 7-13 days old', function () {
    SellerStats::factory()->create([
        'scraped_at' => Carbon::now()->subDays(10),
        'last_attempt_at' => Carbon::now()->subDays(10),
        'consecutive_failures' => 0,
    ]);

    $this->get(route('settings'))->assertInertia(
        fn ($page) => $page->where('sellerStats.status.key', 'stale'),
    );
});

test('status derivation: hidden when scraped_at is 14+ days old or null after first attempt', function () {
    SellerStats::factory()->create([
        'scraped_at' => Carbon::now()->subDays(20),
        'last_attempt_at' => Carbon::now()->subDays(20),
        'consecutive_failures' => 0,
    ]);

    $this->get(route('settings'))->assertInertia(
        fn ($page) => $page->where('sellerStats.status.key', 'hidden'),
    );
});

test('refresh-now POST dispatches the RefreshSellerStats job', function () {
    Bus::fake();

    SellerStats::factory()->create();

    $this->post(route('settings.seller-stats.refresh'))->assertRedirect();

    Bus::assertDispatchedSync(RefreshSellerStats::class);
});

test('view-raw-data is exposed via the Settings page payload', function () {
    SellerStats::factory()->create([
        'rating' => 4.7,
        'review_count' => 220,
    ]);

    $this->get(route('settings'))->assertInertia(
        fn ($page) => $page
            ->has('sellerStats.raw')
            ->where('sellerStats.raw.review_count', 220)
    );
});
