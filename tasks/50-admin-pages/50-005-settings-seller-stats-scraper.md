---
id: "50-005"
title: "Settings — Seller Stats Scraper section (status card + Refresh now)"
status: complete
phase: "50-admin-pages"
size: M
depends_on: ["50-001", "50-003", "phase:30-components", "phase:70-jobs"]
references:
  - docs/ux/settings.md#section-seller-stats-scraper
  - docs/ux/public-homepage.md#what-buyers-say
  - docs/saas-design.md#seller_stats-singleton
  - docs/saas-design.md#seller-stats-scraper
---

## Goal

The third section of the Settings page: a health card for the daily TCGPlayer storefront scraper that maintains the `seller_stats` singleton. Displays last successful / last attempt timestamps, a derived health status (Healthy / Failed / Stale / Hidden), the current values it produced, a "Refresh now" button that dispatches the scraper job, and a "View raw data" modal showing the row JSON for debugging.

## Acceptance criteria

- [x] The `#seller-stats` anchor on `/settings` renders a section with a card titled `"Seller stats scraper"`.
- [x] The card displays:
  - **Last successful scrape** — formatted as `MMM D, YYYY h:mma` plus a relative time (e.g. *"6 days ago"*). Source: `seller_stats.scraped_at`.
  - **Last attempt** — same formatting. Source: `seller_stats.last_attempt_at`.
  - **Status** — derived per the table below, with an icon glyph and a label.
  - **Current values** — Rating (`seller_stats.rating`), Reviews (`seller_stats.review_count`), Feedback comments (`count(seller_stats.feedback)` or `0` when null).
- [x] Status derivation (server-side):

  | Condition | Status | Card treatment |
  |---|---|---|
  | `consecutive_failures = 0` AND `scraped_at` within 7 days | "Healthy" (✅) | Default border |
  | `consecutive_failures ≥ 3` | "Failed N days in a row" (⚠️) — surfaces `last_error` and the message *"Selectors may have changed. Check the storefront page for redesigns."* | Amber border |
  | `scraped_at` is 7–13 days old | "Stale — homepage hides in {N} days" (⚠️) | Amber border |
  | `scraped_at ≥ 14 days` OR (`scraped_at IS NULL` AND scraper has run before, i.e. `last_attempt_at IS NOT NULL`) | "Public section hidden" (🔴) — *"The 'What buyers say' section is no longer rendering on the homepage. Last good scrape: {date}."* | Red border |

- [x] **Refresh now** button: dispatches `App\Jobs\RefreshSellerStats` (or invokes the `seller-stats:refresh` artisan command's job class). Button is disabled while a job is in flight (use a session/cache flag the controller can check; clear the flag when the job completes). On dispatch, show a "Refreshing…" toast; on completion, show the updated card values. Synchronous dispatch is acceptable for v1 per `settings.md §Things to consider`, but document the choice in the commit message and wrap with a `timeout(60)` guard.
- [x] **View raw data** button: opens a PrimeVue Dialog containing the full `seller_stats` row JSON-encoded (pretty-printed). No labels, no formatting — raw `JSON.stringify(row, null, 2)` in a `<pre>` block. Useful for debugging parser output.
- [x] No manual edit form for `seller_stats` — values are scraper-only (per `settings.md §Section: Seller Stats Scraper`).
- [x] Mobile (`< 768px`): the card stacks naturally; both buttons go full-width below the data; the raw-data dialog becomes a full-screen sheet.
- [x] Pest feature test `tests/Feature/Admin/Settings/SellerStatsTest.php` covers:
  - Unauthenticated visit redirects to `/login`.
  - Authenticated visit returns 200 and renders the card with values from a factory-seeded singleton row.
  - Status derivation: assertions for the four states (Healthy / Failed / Stale / Hidden) by varying `scraped_at`, `last_attempt_at`, and `consecutive_failures`.
  - Refresh-now POST dispatches the `RefreshSellerStats` job (use `Bus::fake()` to assert dispatch).
  - View-raw-data endpoint (or page payload) exposes the singleton row contents.
- [x] `composer test` passes.

## Implementation notes

- The `seller_stats` table, the `RefreshSellerStats` job, and the `seller-stats:refresh` artisan command are owned by phase 70. This task **consumes** them — does not implement them. The `phase:70-jobs` dependency covers it.
- Status derivation is best done in the controller (or a small helper / service class) rather than the Vue component, so the test can assert against the derived value directly without DOM scraping.
- "Refresh now" should ideally dispatch async and the page polls for completion, per `settings.md §Things to consider` — but synchronous dispatch with a 60-second timeout is acceptable for v1. Either is fine; pick one and document.
- The "in-flight" flag for disabling the button: a cache key like `seller-stats:refreshing` set on dispatch, cleared by the job's `finally`/`failed` lifecycle. Inertia exposes the flag via shared props.
- Use `pi pi-check-circle`, `pi pi-exclamation-triangle`, `pi pi-times-circle` for the status icons (or equivalent). Keep colors per the semantic palette in `ux-patterns.md` — green / amber / red.
- Since the homepage hides the `What buyers say` section at day 14, the `Stale — homepage hides in {N} days` countdown is the operator's early warning. Compute `N = 14 - days_since_scraped_at`.

## Out of scope

- The `seller_stats` table schema (phase 10 or wherever the migration lives).
- The scraper job and artisan command (`phase:70-jobs`).
- A manual-edit fallback form for the singleton (deferred per `settings.md §Section: Seller Stats Scraper`).
- Pricing Rules section (`50-003`).
- File History section (`50-004`).
- Email or push alerts on scraper failure (intentionally absent per `tasks/README.md §Project decisions`).
