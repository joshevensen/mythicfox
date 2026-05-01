---
id: "10-006"
title: "Implement MyPricing CSV importer with bootstrap and reconciliation modes"
status: pending
phase: "10-catalog"
size: L
depends_on: ["10-004", "10-005"]
references:
  - docs/catalog-schema.md#mypricing-import
  - docs/catalog-schema.md#column--field-map-pricingcustomexport--mypricing-share-this
  - docs/catalog-schema.md#things-to-consider
---

## Goal

Build the importer for `MyPricing.csv` — TCGPlayer's export of the seller's *live listings*. It runs in two distinct modes selected at upload time: **bootstrap** (one-shot at launch — also seeds `inventory.quantity` from `Total Quantity`) and **reconciliation** (read-only drift report). Both share the same column map; only the persistence behavior differs.

## Acceptance criteria

- [ ] A service class (e.g. `App\Services\Catalog\MyPricingImporter`) accepts a file path and a mode enum (`MyPricingImportMode::Bootstrap` or `MyPricingImportMode::Reconciliation`) and returns a result object.
- [ ] **Bootstrap mode**:
  - Performs the same product / set / card upserts as `10-005`'s `PricingCustomExportImporter` — do NOT duplicate code; extract the shared catalog-upsert logic into a helper or reuse the existing service.
  - For each row, looks up the `card` by `tcgplayer_id` and upserts an `inventory` row keyed on `card_id` with `quantity = Total Quantity`.
  - **`TCG Marketplace Price` is ignored.** `override_price` stays null; `calculated_price` stays unset until the first pricing export computes it.
  - Idempotent — re-running the same file does not double-count quantities (it overwrites, not appends).
- [ ] **Reconciliation mode**:
  - **Read-only.** Writes nothing to `cards` or `inventory`.
  - For each row, compares: TCGPlayer's `Total Quantity` vs local `inventory.quantity`; TCGPlayer's `TCG Marketplace Price` (cents) vs local effective price (`COALESCE(override_price, calculated_price)`).
  - Returns a structured discrepancy report with three buckets: rows where local differs from TCGPlayer (in either direction, in quantity or price), rows in MyPricing but missing locally (no `cards` match for `tcgplayer_id`), rows local-only (in `inventory` but not in the uploaded CSV).
  - Persists the uploaded file via `files` for audit even though no DB writes happen.
- [ ] Both modes persist the source CSV via `files` with `purpose = pricing` and original filename preserved.
- [ ] Decimal-to-cents parsing is shared with `10-005` (extract the helper if not already shared).
- [ ] An Artisan command `catalog:import-mypricing {path} {--mode=bootstrap|reconcile}` wraps the service. Default mode is **reconcile** (the safer one) — bootstrap requires explicit `--mode=bootstrap` per the doc's note that bootstrap is a one-shot.
- [ ] Pest feature tests cover, with a small fixture (`tests/fixtures/catalog/mypricing-sample.csv`):
  - Bootstrap mode: catalog upserts happen, inventory rows are created with correct quantities, `TCG Marketplace Price` does NOT land in `override_price`, re-running does not double-count.
  - Reconciliation mode: returns discrepancies for a fixture where local and CSV deliberately disagree on quantity, on price, and where rows are missing on each side; writes nothing to `inventory`.
- [ ] `composer test` passes.

## Implementation notes

- Reuse the `10-005` catalog-upsert helper. The two importers diverge only in inventory handling.
- Bootstrap is destructive in the sense that it can clobber local `quantity` edits — surface this prominently in the eventual Settings UI (phase 50) but the Artisan command should at minimum print a `--confirm` prompt or require `--force` when ran a second time. A simple guard: refuse bootstrap if any `inventory` row already exists, unless `--force` is passed. Document the chosen behavior.
- Reconciliation report output: structured array / DTO is fine for now (phase 50 will render it on the Settings page). Don't build HTML.
- Empty `Total Quantity` cells in MyPricing should be treated as 0, not null — TCGPlayer's export emits a number for every owned listing.

## Out of scope

- Settings-page UI for uploading and choosing mode — phase 50.
- Email or push notifications about reconciliation discrepancies — out of scope per project decisions.
- Background queueing — same rationale as `10-005`.
