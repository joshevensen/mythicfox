---
id: "40-002"
title: "Public homepage at `/` — hero, about, what-you-get, what-buyers-say"
status: pending
phase: "40-public-pages"
size: L
depends_on: ["40-001", "phase:30-components", "phase:70-jobs"]
references:
  - docs/ux/public-homepage.md#layout
  - docs/ux/public-homepage.md#hero
  - docs/ux/public-homepage.md#about
  - docs/ux/public-homepage.md#what-you-get
  - docs/ux/public-homepage.md#what-buyers-say
  - docs/ux/public-homepage.md#seo
  - docs/ux/public-homepage.md#data
  - docs/saas-design.md#seller_stats-singleton
---

## Goal

Build the unauthenticated landing page at `/`. Hero with logo + tagline + "Shop on TCGPlayer" CTA, about paragraph, four "what you get" feature blocks, and the "what buyers say" section pulled from the `seller_stats` singleton (with the explicit hide-if-stale rules). All static copy lives in the Vue component; the only dynamic data is `seller_stats`.

## Acceptance criteria

- [ ] Route `GET /` registered (public, no auth middleware) renders an Inertia page that uses `PublicLayout` from `40-001`.
- [ ] Page title is `"Mythic Fox Games — Buy & Sell Trading Card Games"` and the meta description matches `public-homepage.md §SEO`.
- [ ] Hero renders: Mythic Fox logo, the tagline `"We buy & sell trading card games."`, and a primary CTA button "Shop on TCGPlayer →" linking to `config('services.tcgplayer.storefront_url')` with `target="_blank" rel="noopener"`. The URL is shared via Inertia props (e.g. `tcgplayerStorefrontUrl`), not hardcoded in markup.
- [ ] About section renders the paragraph from `public-homepage.md §About` verbatim.
- [ ] "What you get" section renders the four feature blocks (Pack-fresh inventory / Honest condition / Protective packaging / Fast shipping) each with a PrimeIcon. Layout: 1 col on phone, 2×2 on tablet, 1×4 on desktop.
- [ ] "What buyers say" section reads `seller_stats` (singleton):
  - Section is **hidden** when `seller_stats.scraped_at IS NULL` OR `scraped_at < now() - 14 days`.
  - When visible, shows `⭐ {rating} from {review_count} reviews on TCGPlayer` using `pi pi-star-fill` / `pi pi-star` for the glyphs (rating value + count from the row).
  - When `feedback` is non-empty, renders up to 3 quote cards (text, reviewer name, date). When empty, just rating + count.
  - The whole section is NOT a clickable link.
- [ ] JSON-LD `Organization` structured-data block emitted in the page head with name, url, logo.
- [ ] `seller_stats` data is supplied by an Inertia controller (e.g. `PublicHomepageController@__invoke`) that queries the singleton and applies the staleness rule server-side — the Vue component receives either `null` or a populated `sellerStats` prop and just renders accordingly.
- [ ] Pest feature test `tests/Feature/Public/HomepageTest.php` covers:
  - Anonymous request returns 200.
  - Page renders with the expected title in HTML.
  - Stat section is hidden when no `seller_stats` row exists.
  - Stat section is hidden when `scraped_at` is older than 14 days.
  - Stat section renders rating + review count when fresh (set up via factory).
  - Storefront URL from config appears in the hero CTA's href.
- [ ] `composer test` passes.

## Implementation notes

- `seller_stats` is a singleton — phase 10 (or wherever it lives) creates the table; phase 70 owns the scraper job and the `consecutive_failures` / `last_attempt_at` columns. This task only **reads** from the row.
- The hide-if-stale logic should live in the controller, not the Vue component — the component just renders or doesn't render based on a single boolean prop (`showBuyersSay`) plus the data.
- All static copy (hero tagline, about paragraph, feature block titles + descriptions, footer year) lives in the `.vue` component. Updates ship via deploy.
- Card layout for "what buyers say": 1 column on phone, 1×3 on tablet/desktop. Use the brand colors per `ux-patterns.md` (quote text: `mf-brown`, stars: `mf-orange`).
- Use Wayfinder typed routes for the internal `/login` link (in the footer if `40-004` hasn't shipped yet, otherwise that's `40-004`'s problem).
- No analytics, no OG image — both intentionally out of scope per `public-homepage.md §Things to consider`.
- The `tcgplayer.storefront_url` config key was added in `00-004` (foundation). Reference it; don't redefine.

## Out of scope

- Building `seller_stats` table / scraper job (phase 70 — `phase:70-jobs` dep covers it).
- The public layout chrome (that's `40-001`).
- Footer markup including the `[Admin]` link to `/login` (that's `40-004`).
- `/sitemap.xml`, `/robots.txt` (that's `40-004`).
- A buy-list contact form (deferred, not v1).
- OG image / Twitter card metadata (intentionally descoped).
