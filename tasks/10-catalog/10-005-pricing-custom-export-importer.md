---
id: "10-005"
title: "Implement PricingCustomExport CSV importer (catalog seed + market-price refresh)"
status: pending
phase: "10-catalog"
size: L
depends_on: ["10-001", "10-002", "10-003"]
references:
  - docs/catalog-schema.md#pricingcustomexport-import
  - docs/catalog-schema.md#column--field-map-pricingcustomexport--mypricing-share-this
  - docs/catalog-schema.md#source-files
  - docs/saas-design.md#files
---

## Goal

Build the importer for `PricingCustomExport.csv` — TCGPlayer's full filtered catalog dump. This is how new cards enter the system and how `cards.market_price` / `cards.low_price` get refreshed for pricing runs. The importer must be idempotent: re-running the same file produces no logical changes beyond the inevitable price drift.

## Acceptance criteria

- [ ] A service class (e.g. `App\Services\Catalog\PricingCustomExportImporter`) accepts a file path (or stream) and returns a result object summarizing rows processed, products touched, sets created, cards inserted/updated.
- [ ] The service performs the upserts in the order defined by `docs/catalog-schema.md#pricingcustomexport-import`:
  1. Persist the uploaded file via the `files` table (path: `imports/pricing/YYYY/MM/{ulid}-{slug}.csv` per `docs/saas-design.md#path-convention`).
  2. Upsert `products` by `name` — do NOT touch pricing-rule fields on update.
  3. Upsert `sets` by `(product_id, name)` — do NOT touch override fields on update.
  4. Upsert `cards` by `tcgplayer_id` — on insert populate identity + market/low; on update **only refresh `market_price` and `low_price`**, never identity.
  5. After all rows, set `products.priced_at = now()` for every product touched in this run.
- [ ] Decimal `TCG Market Price` / `TCG Low Price` columns are parsed to integer cents. Empty cells become null. Missing-but-valid prices (e.g. blank `TCG Low Price`) do not abort the row.
- [ ] Columns explicitly listed as "Not stored" / "Ignored" in the doc's column map are dropped silently (notably `Total Quantity`, `TCG Marketplace Price`, `TCG Direct Low`, `TCG Low Price With Shipping`, `Title`, `Add to Quantity`, `Photo URL`).
- [ ] Importer streams the CSV (don't load 100k+ rows into memory). Batch upserts in chunks of ~500.
- [ ] Wraps each chunk in a transaction; failures roll back the chunk and continue with a logged error per the operator's tolerance — or aborts the whole run with a clear summary. Pick one and document the choice in the implementation notes section of this task's commit message.
- [ ] Pest feature test uses a small fixture CSV (5–10 rows, multiple products + sets + conditions) at `tests/fixtures/catalog/pricing-custom-export-sample.csv` and asserts: products / sets / cards are created with correct values; re-running the importer with the same file produces zero logical changes (same row counts, prices match); a second run with one row's `TCG Market Price` changed updates only that card's `market_price`; `products.priced_at` is bumped on every run.
- [ ] An Artisan command `catalog:import-pricing-custom-export {path}` wraps the service for ad-hoc local use.
- [ ] `composer test` passes.

## Implementation notes

- Use `League\Csv` (already in Laravel ecosystem) or PHP's native `SplFileObject` for streaming. The 103MB Magic dump cannot be `array_map`-ed.
- `tcgplayer_id` is the upsert key for cards — Postgres `INSERT ... ON CONFLICT (tcgplayer_id) DO UPDATE` via Eloquent's `upsert()` method works cleanly.
- Identity-protection on update (don't overwrite `product_name`, `number`, `rarity`, `condition`) is critical — the doc calls this out explicitly. The simplest implementation is to `upsert([...], ['tcgplayer_id'], ['market_price', 'low_price'])` so only the third-arg columns are updated on conflict.
- Don't store `TCG Marketplace Price` even on insert. Pricing is owned by the algorithm; preserving the seller's historical TCGPlayer prices during catalog seeding would muddle the bootstrap.
- The `files` row is created **once per import run** (one CSV → one `files` row), regardless of how many rows it contains.
- Keep the small fixture in the repo. The 103MB real dump must NOT be checked in (per the doc's "Things to consider").

## Out of scope

- The MyPricing importer — `10-006`.
- The pricing algorithm or recompute — `10-007`.
- A web UI for uploading the CSV — phase 50 (Add Cards / Settings).
- Background-queue dispatch for large imports — possibly added in phase 70 if synchronous import becomes painful; for now run inline via the Artisan command.
