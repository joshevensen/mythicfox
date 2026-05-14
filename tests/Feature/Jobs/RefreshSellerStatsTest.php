<?php

use App\Jobs\RefreshSellerStats;
use App\Models\SellerStats;
use App\Services\SellerStats\StorefrontFetcher;

// ── Helpers ───────────────────────────────────────────────────

function fakeFetcher(string $html = '<html></html>'): StorefrontFetcher
{
    return new class($html) implements StorefrontFetcher
    {
        public function __construct(private readonly string $html) {}

        public function fetchHtml(string $url): string
        {
            return $this->html;
        }
    };
}

function throwingFetcher(): StorefrontFetcher
{
    return new class implements StorefrontFetcher
    {
        public function fetchHtml(string $url): string
        {
            throw new RuntimeException('Browsershot failed: Chrome not found');
        }
    };
}

function bindFetcher(StorefrontFetcher $fetcher): void
{
    app()->instance(StorefrontFetcher::class, $fetcher);
}

function fixtureHtml(): string
{
    return (string) file_get_contents(base_path('tests/fixtures/tcgplayer-storefront.html'));
}

// ── Success path ──────────────────────────────────────────────

test('success path: updates all six columns and resets failures', function () {
    SellerStats::factory()->create([
        'rating' => 4.5,
        'review_count' => 100,
        'consecutive_failures' => 2,
        'last_error' => 'old error',
    ]);

    bindFetcher(fakeFetcher(fixtureHtml()));

    dispatch_sync(new RefreshSellerStats);

    $stats = SellerStats::first();

    expect($stats->rating)->toBe(4.9)
        ->and($stats->review_count)->toBe(1234)
        ->and($stats->feedback)->toBeArray()
        ->and($stats->scraped_at)->not->toBeNull()
        ->and($stats->last_attempt_at)->not->toBeNull()
        ->and($stats->last_error)->toBeNull()
        ->and($stats->consecutive_failures)->toBe(0);
});

test('success preserves prior feedback when parser finds no comment text', function () {
    $priorFeedback = [['text' => 'prior comment', 'rating' => 5, 'author' => 'alice', 'date' => 'Jan 2025']];

    SellerStats::factory()->create([
        'feedback' => $priorFeedback,
        'consecutive_failures' => 0,
    ]);

    // HTML has rating/count but no feedback items
    $html = <<<'HTML'
    <html><body>
      <span class="seller-rating__average">4.9</span>
      <span class="seller-rating__count">1,234 ratings</span>
    </body></html>
    HTML;

    bindFetcher(fakeFetcher($html));

    dispatch_sync(new RefreshSellerStats);

    $stats = SellerStats::first();

    expect($stats->feedback)->toBe($priorFeedback);
});

// ── Failure path ──────────────────────────────────────────────

test('failure path: leaves rating/review_count/feedback untouched and increments failures', function () {
    SellerStats::factory()->create([
        'rating' => 4.7,
        'review_count' => 200,
        'feedback' => [['text' => 'good', 'rating' => 5, 'author' => 'bob', 'date' => 'Feb 2025']],
        'scraped_at' => now()->subDay(),
        'consecutive_failures' => 2,
    ]);

    bindFetcher(throwingFetcher());

    dispatch_sync(new RefreshSellerStats);

    $stats = SellerStats::first();

    expect((float) $stats->rating)->toBe(4.7)
        ->and($stats->review_count)->toBe(200)
        ->and($stats->feedback[0]['text'])->toBe('good')
        ->and($stats->last_error)->toContain('Chrome not found')
        ->and($stats->consecutive_failures)->toBe(3);
});

test('failure path: no rating from parser increments failures', function () {
    SellerStats::factory()->create(['consecutive_failures' => 0]);

    // Empty HTML → parser returns null rating → job treats as failure
    bindFetcher(fakeFetcher('<html><body></body></html>'));

    dispatch_sync(new RefreshSellerStats);

    $stats = SellerStats::first();

    expect($stats->consecutive_failures)->toBe(1)
        ->and($stats->last_error)->toContain('no rating');
});

test('failure path with no prior success: scraped_at stays null, failures increment from 0 to 1', function () {
    SellerStats::factory()->create([
        'scraped_at' => null,
        'consecutive_failures' => 0,
    ]);

    bindFetcher(throwingFetcher());

    dispatch_sync(new RefreshSellerStats);

    $stats = SellerStats::first();

    expect($stats->scraped_at)->toBeNull()
        ->and($stats->consecutive_failures)->toBe(1)
        ->and($stats->last_attempt_at)->not->toBeNull();
});

// ── Singleton enforcement ─────────────────────────────────────

test('running the job twice does not create a second row', function () {
    bindFetcher(fakeFetcher(fixtureHtml()));

    dispatch_sync(new RefreshSellerStats);
    dispatch_sync(new RefreshSellerStats);

    expect(SellerStats::count())->toBe(1);
});

test('job creates the singleton row on first run if it does not exist', function () {
    expect(SellerStats::count())->toBe(0);

    bindFetcher(fakeFetcher(fixtureHtml()));

    dispatch_sync(new RefreshSellerStats);

    expect(SellerStats::count())->toBe(1);
});

// ── Console command ───────────────────────────────────────────

test('artisan seller-stats:refresh command dispatches the job synchronously', function () {
    SellerStats::factory()->create(['consecutive_failures' => 0]);

    bindFetcher(fakeFetcher(fixtureHtml()));

    $this->artisan('seller-stats:refresh')->assertSuccessful();

    expect(SellerStats::first()->scraped_at)->not->toBeNull();
});
