---
id: "10-009"
title: "Implement MyPricing CSV exporter (round-trip back to TCGPlayer)"
status: complete
phase: "10-catalog"
size: M
depends_on: ["10-008"]
references:
  - docs/catalog-schema.md#pricing-export
  - docs/catalog-schema.md#output-column-map
  - docs/catalog-schema.md#trigger-and-behavior
  - docs/saas-design.md#path-convention
---

## Goal

Generate the outbound CSV in MyPricing format that the seller uploads back to TCGPlayer. The exporter runs after the recompute (`10-008`), emits one row per `inventory` entry with the exact 16-column header, persists the file via the `files` table, and updates `inventory.last_exported_price` on every row to set the next preview's diff baseline.

## Acceptance criteria

- [x] A service class (e.g. `App\Services\Catalog\PricingExporter`) exposes a method that, given a result of the recompute service or a fresh trigger, generates a CSV and returns `(File $fileRecord, string $storagePath)`.
- [x] CSV header matches the 16 columns of the MyPricing format **in the exact order TCGPlayer emits**: `TCGplayer Id, Product Line, Set Name, Product Name, Title, Number, Rarity, Condition, TCG Market Price, TCG Direct Low, TCG Low Price With Shipping, TCG Low Price, Total Quantity, Add to Quantity, TCG Marketplace Price, Photo URL`.
- [x] Each row is sourced per `docs/catalog-schema.md#output-column-map`:
  - `TCGplayer Id`, `Product Line`, `Set Name`, `Product Name`, `Number`, `Rarity`, `Condition` from the catalog.
  - `Title`, `TCG Direct Low`, `TCG Low Price With Shipping`, `Photo URL` are emitted **empty** (intentional — see doc).
  - `TCG Market Price`, `TCG Low Price` are formatted from cents back to a 2-decimal string (`1234` cents → `12.34`). Empty cells where the source is null.
  - `Total Quantity` from `inventory.quantity`.
  - `Add to Quantity` is literally `0` on every row.
  - `TCG Marketplace Price` is `COALESCE(override_price, calculated_price)` formatted as a 2-decimal string; empty if both are null.
- [x] One row is emitted **per inventory entry**, regardless of `quantity` (zero-qty rows still hold meaningful state per `docs/ux/inventory.md`).
- [x] After successful CSV write: persist a `files` row at `exports/pricing/YYYY/MM/{ulid}-mythic-fox-pricing.csv` per `docs/saas-design.md#path-convention`, then update `inventory.last_exported_price = COALESCE(override_price, calculated_price)` for every row in a single transaction.
- [x] If the CSV write fails (disk error, etc.), `last_exported_price` is **not** touched — the next preview must still surface the same diff.
- [x] An Artisan command `catalog:export-pricing` runs the full flow: recompute → export → save file → update `last_exported_price`. Emits the resulting file path.
- [x] Pest feature tests cover, with a seeded mix of inventory rows (some with overrides, some without, some with null pricing, some with quantity 0):
  - Header row matches the 16-column ordered list verbatim.
  - Each row's `TCG Marketplace Price` reflects override-precedence and emits empty when both are null.
  - Decimal formatting round-trips (12.34 ↔ 1234 cents).
  - `Add to Quantity` is `0` on every row.
  - `last_exported_price` matches each row's effective price after a successful export.
  - A simulated mid-export failure does not update `last_exported_price` on any row.
- [x] First-export round-trip note: per the doc, verify on the first real production export whether TCGPlayer accepts empty cells for `TCG Direct Low` and `TCG Low Price With Shipping`. Add a TODO comment in the exporter referencing this — if TCGPlayer rejects them, a follow-up task can backfill with `TCG Low Price`.
- [x] `composer test` passes.

## Implementation notes

- Stream-write the CSV to a temp file on local disk first, then move/upload to the `files` storage path. With 100k+ inventory rows you don't want this in memory.
- Use `League\Csv` (or `fputcsv`) for output. RFC 4180 quoting; UTF-8 with no BOM (TCGPlayer's input doesn't use one — match it exactly).
- Cents-to-decimal formatting: `number_format($cents / 100, 2, '.', '')` — confirm no thousands separator, period as decimal mark.
- The recompute step is invoked via `InventoryRecomputeService` from `10-008` — don't duplicate the algorithm logic.
- The `last_exported_price` update happens inside the **same** outer call as the file persistence. Both succeed or both fail. Per the doc: "On Cancel, recompute already happened, but `last_exported_price` is not touched" — the inventory-page Cancel button is UI-side (phase 60); from this service's POV the contract is "if the caller invokes export, both the file and the baseline update commit together."

## Out of scope

- The preview modal — phase 60.
- Auto-uploading the CSV to TCGPlayer — not part of v1; the seller manually uploads.
- Per-product / filtered exports — v1 exports all of inventory.
- Adjusting empty-cell behavior for `TCG Direct Low` etc. based on real-world rejection — that's a phase 60 follow-up if it happens.
