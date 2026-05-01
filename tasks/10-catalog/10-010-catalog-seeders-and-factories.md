---
id: "10-010"
title: "Add catalog domain seeders and shared factory states for downstream phases"
status: pending
phase: "10-catalog"
size: S
depends_on: ["10-001", "10-002", "10-003", "10-004"]
references:
  - docs/catalog-schema.md#products
  - docs/catalog-schema.md#sets
  - docs/catalog-schema.md#cards
  - docs/catalog-schema.md#default-product-values
  - docs/catalog-schema.md#condition-vocabulary
---

## Goal

Provide realistic catalog seed data and well-named factory states so phase 20 (orders), phase 50 (admin pages), and phase 60 (data pages) have something to render against without re-deriving fixtures. This task is intentionally modest in production-DB impact: it seeds the *three known products* with default rules and provides factory states for everything else; it does NOT seed thousands of cards.

## Acceptance criteria

- [ ] `database/seeders/CatalogSeeder.php` (or similar) seeds the three production products with their canonical TCGPlayer "Product Line" names verbatim: `Magic`, `Lorcana TCG`, `Flesh & Blood TCG`. Uses default pricing-rule values from `docs/catalog-schema.md#default-product-values`.
- [ ] Seeder is **idempotent** — re-running does not duplicate rows. Use `firstOrCreate` keyed on `name`.
- [ ] Seeder is registered in `DatabaseSeeder` and runs cleanly under `php artisan db:seed --class=CatalogSeeder`.
- [ ] `ProductFactory`, `SetFactory`, `CardFactory`, `InventoryFactory` (from earlier tasks) are augmented with named states:
  - `ProductFactory::magic()`, `::lorcana()`, `::fleshAndBlood()` — return rows matching the canonical names.
  - `SetFactory::forProduct(Product $p)` — convenience for nested factories.
  - `CardFactory::condition(string $condition)` — accepts any of the 11 strings from `docs/catalog-schema.md#condition-vocabulary`. Default state picks `Near Mint`.
  - `CardFactory::nearMint()`, `::nearMintFoil()` — common shortcuts.
  - `CardFactory::withMarketAndLow(int $marketCents, ?int $lowCents)` — for pricing-algorithm tests.
  - `InventoryFactory::withOverride(int $cents)`, `::withCalculated(int $cents)`, `::lastExported(int $cents)` — for export-flow tests.
- [ ] A second seeder `DemoCatalogSeeder` (only run via `--class=DemoCatalogSeeder`, NOT from the default `DatabaseSeeder`) creates a small but realistic demo dataset: 2 sets per product, ~20 cards per set across multiple conditions, ~10 inventory rows per product. Useful for screenshotting / local dev / manual UI exploration in later phases.
- [ ] Pest test asserts: running `CatalogSeeder` twice yields exactly three products with the canonical names; the demo seeder produces the expected row counts and all FKs resolve.
- [ ] `composer test` passes.

## Implementation notes

- Card numbers in the demo seeder should use a plausible per-game format (`BOL022` style for Flesh & Blood, `97/204` for Magic, `292` for Lorcana) — phase 60's catalog UI will render these literally.
- Rarity strings in the demo seeder must match each game's vocabulary verbatim per the doc — Magic uses single letters (`C/U/R/M`), Lorcana / Flesh & Blood use words. Tests should assert at least one Magic card has `R` rarity and at least one Flesh & Blood card has `Majestic`.
- Demo `tcgplayer_id` values can be sequential synthetic integers — pick a range outside what real TCGPlayer ids occupy if possible (e.g. starting at 9_000_000) to make them obviously synthetic.
- Demo inventory should include at least one row with an `override_price` set, at least one with a null `calculated_price` (both source prices null), and at least one with `quantity = 0` — these edge cases drive the inventory page's UI.

## Out of scope

- Importing real TCGPlayer fixture CSVs as part of the seeder — `10-005` and `10-006` already cover real-data flows via Artisan.
- Production data backfill — the operator runs the real importers against real CSVs; this task only ensures the three product rows exist.
- Test-fixture CSVs for the importers — those are owned by `10-005` and `10-006` and live under `tests/fixtures/catalog/`.
- Order-side seed data — phase 20.
