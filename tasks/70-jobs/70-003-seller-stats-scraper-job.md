---
id: "70-003"
title: "RefreshSellerStats scraper job (daily, Browsershot, failure tracking)"
status: complete
phase: "70-jobs"
size: L
depends_on: ["phase:00-foundation"]
references:
  - docs/saas-design.md#seller-stats-scraper
  - docs/saas-design.md#seller_stats-singleton
  - docs/saas-design.md#scheduled-jobs
  - docs/ux/settings.md#section-seller-stats-scraper
---

## Goal

Maintain the `seller_stats` singleton row by scraping the public TCGPlayer storefront once daily. The job renders the JS-mounted seller page in headless Chrome (Browsershot), parses out rating / review count / recent feedback comments, and updates the row. On failure, it preserves the last-good values, records the error, and increments a consecutive-failure counter that the Settings page surfaces to the operator. Per [saas-design.md §Seller stats scraper](../../docs/saas-design.md#seller-stats-scraper), this is the **only** scraper in the system and is intentionally polite (one request/day, no retry on failure).

## Acceptance criteria

- [x] `App\Jobs\RefreshSellerStats` job exists, implements `ShouldQueue`, and writes to the `seller_stats` singleton (id=1).
- [x] `php artisan seller-stats:refresh` console command dispatches the job synchronously (`->onConnection('sync')`) so the Settings page's "Refresh now" button can invoke it and observe results immediately. Per [ux/settings.md §Things to consider](../../docs/ux/settings.md), an async-with-polling variant is a future improvement, not v1.
- [x] Scheduler entry in `routes/console.php` (or `app/Console/Kernel.php`) runs `seller-stats:refresh` **daily** at a fixed hour (suggest 6:00 AM server time — pick one and document in this task's commit). Per [§Scheduled jobs](../../docs/saas-design.md#scheduled-jobs).
- [x] `App\Services\TcgplayerStorefrontParser` (or similarly named small class) encapsulates the HTML → `{ rating, review_count, feedback }` extraction. The job calls Browsershot, hands the rendered HTML to the parser, and writes the result. Selector breakage is contained to this class per [§Seller stats scraper](../../docs/saas-design.md#seller-stats-scraper).
- [x] Storefront URL pulled from `config('services.tcgplayer.storefront_url')` (added in `00-004`).
- [x] Browsershot is invoked with a `User-Agent` that identifies the scraper (e.g. `Mythic Fox Games / seller-stats-bot`) per [§Seller stats scraper §Politeness](../../docs/saas-design.md#seller-stats-scraper).
- [x] **On success**: the job
  - [x] Updates `rating`, `review_count`, and `feedback` (only updates `feedback` when the parser found comment text — null/empty preserves prior).
  - [x] Sets `scraped_at = now()`, `last_attempt_at = now()`, `last_error = null`, `consecutive_failures = 0`.
- [x] **On failure** (Browsershot throws, parser throws, or parser returns no rating): the job
  - [x] Leaves `rating`, `review_count`, `feedback` untouched.
  - [x] Sets `last_attempt_at = now()`, populates `last_error` with the exception message (truncated to a sensible length), increments `consecutive_failures` by 1.
  - [x] **Does not retry** within the same run — failure is logged, the job exits cleanly. The next scheduled run tomorrow is the retry.
- [x] Pest feature tests cover:
  - [x] **Success path**: parser is mocked / fixture-fed to return `{ rating: 4.9, review_count: 1234, feedback: [...] }`; job runs; assert all six success-path columns updated correctly and `consecutive_failures` reset from a non-zero starting value.
  - [x] **Success preserves feedback when parser finds no comments**: parser returns rating + review_count but `feedback: null`; assert prior `feedback` column value is preserved (NOT overwritten with null).
  - [x] **Failure path**: parser/Browsershot throws; assert `rating`/`review_count`/`feedback` unchanged from a seeded prior-good state, `last_error` is populated, `consecutive_failures` incremented from 2 to 3.
  - [x] **Failure path with no prior success**: scraper never ran before (`scraped_at IS NULL`); failure leaves `scraped_at` null and increments `consecutive_failures` from 0 to 1.
  - [x] **Singleton enforcement**: running the job twice does not create a second row.
  - [x] **Console command** invocation: `Artisan::call('seller-stats:refresh')` dispatches the job (test with a fake queue or by asserting the row was updated synchronously).
- [x] The parser class has its own unit test fed by an HTML fixture (a snippet of representative storefront markup) under `tests/Fixtures/tcgplayer-storefront.html`. The fixture is committed; selectors in the parser map to the fixture.
- [x] `composer test` passes.

## Implementation notes

- Browsershot's `Browsershot::url($url)->bodyHtml()` returns the post-JS-render DOM. Use `setUserAgent(...)` and a reasonable timeout (~30s — TCGPlayer can be slow). Do not configure aggressive retries inside the job; the daily cadence IS the retry policy.
- The `seller_stats` singleton row is created with `Model::firstOrCreate(['id' => 1], [...])` so the very first scrape works even if the row hasn't been seeded.
- For tests, swap Browsershot via a service binding so the success-path test doesn't actually hit the network. A trivial `StorefrontFetcher` interface with a Browsershot-backed default and a fake binding for tests is a clean pattern.
- `consecutive_failures = 3` is the threshold the Settings page uses to flag selector breakage per [ux/settings.md](../../docs/ux/settings.md). The job itself only increments the counter — the UI does the comparison.
- Do NOT email or otherwise notify on failure; outbound email is explicitly disabled per [README.md](../README.md). The Settings page is the only signal channel.
- `last_error` should be the exception class + message, e.g. `RuntimeException: Selector ".rating-value" not found`. Capping at ~500 chars is fine — full stack traces don't belong in the database column.

## Out of scope

- Manual edit form for `seller_stats` (the Option-A fallback if scraping is permanently blocked) — explicitly future per [ux/settings.md](../../docs/ux/settings.md).
- Async dispatch + polling for the "Refresh now" button — explicitly v2 per [ux/settings.md §Things to consider](../../docs/ux/settings.md).
- Email/Slack alerting on failure — outbound email is disabled.
- Retry within a single run — politeness rules forbid it.
- Settings-page UI itself — that lives in `phase:50-admin-pages`.
