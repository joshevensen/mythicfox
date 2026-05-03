---
id: "60-007"
title: "Inventory page — Export Pricing flow (recompute → preview modal → CSV download)"
status: complete
phase: "62-inventory-pages"
size: L
depends_on:
  - "60-006"
  - "phase:10-catalog"
  - "phase:30-components"
references:
  - docs/ux/inventory.md#export-pricing-flow
  - docs/ux/inventory.md#schema-addition
  - docs/ux/inventory.md#states
  - docs/catalog-schema.md#pricing-export
  - docs/catalog-schema.md#output-column-map
---

## Goal

Wire the **Export Pricing** primary button on the Inventory page to the full round-trip: server runs the dual-input pricing algorithm against every `inventory` row, opens a preview modal showing diffs vs the last exported price, and on confirm generates the MyPricing CSV, persists it via `files`, triggers download, and updates `last_exported_price`. Includes the `last_exported_price` column migration (the only consumer is this task).

## Acceptance criteria

- [x] Migration adds nullable integer `last_exported_price` column to the `inventory` table (cents). Index not required.
- [x] `App\Models\Inventory` (or equivalent) `$fillable` and `$casts` updated to include `last_exported_price`.
- [x] Clicking **Export Pricing** triggers Step 1 (recompute):
  - Server runs the dual-input pricing algorithm per `docs/catalog-schema.md#pricing-algorithm` against every `inventory` row (not just the filtered view).
  - Persists results to `inventory.calculated_price`. Never touches `override_price`.
  - Button shows spinner during recompute.
- [x] After recompute, Step 2 (preview modal) opens — PrimeVue `Dialog` ~80% width, title **"Pricing changes"**:
  - Subtitle: *"N rows have changed effective prices since your last export."* (or *"No price changes since your last export."* if none).
  - Toggle ☐ Show all rows (default off → only rows where `last_exported_price ≠ COALESCE(override_price, calculated_price)`).
  - Table with columns: Card (`MfCardIdentity compact`: Name · #Number · Set · Condition), Old (`last_exported_price`, `—` if null), New (`COALESCE(override_price, calculated_price)`), Δ (computed delta in cents, green up / red down / neutral `—`).
  - Footer: **Cancel** (closes modal — recompute already happened so `calculated_price` stays updated; `last_exported_price` unchanged) and **Download CSV** (primary).
- [x] **Download CSV** click:
  - Generates the MyPricing-format CSV per `docs/catalog-schema.md#output-column-map` using the current effective price for every inventory row.
  - Persists the generated file via the `files` table using `App\Support\FilePath` (purpose `pricing`, type `exports`).
  - Triggers a browser download of the CSV.
  - Updates `inventory.last_exported_price = COALESCE(override_price, calculated_price)` for every row.
  - Closes modal; toast: *"Pricing CSV downloaded — N rows, M changed."*
- [x] **First-ever export** (no prior `last_exported_price` anywhere) renders a one-time banner inside the preview modal: *"First export — every row will be set as the new baseline."* per `docs/ux/inventory.md#things-to-consider`.
- [x] **Cancel** after recompute closes the modal cleanly. Does NOT update `last_exported_price`. The next preview will still show the same diff against the old baseline — confirmed by test.
- [x] **Download failure** (CSV write or storage error) leaves the modal open with an inline error banner; `last_exported_price` is **not** updated; user can retry per `docs/ux/inventory.md#states`.
- [x] Mobile: Export Pricing primary button sticks to the bottom of the viewport on phones; preview modal becomes a full-screen sheet; the diff table scrolls horizontally if columns overflow.
- [x] Pest feature test: POST to the recompute endpoint runs the algorithm and updates `calculated_price` on a seeded inventory row.
- [x] Pest feature test: GET on the preview-data endpoint returns only rows where current effective ≠ last_exported_price (when toggle is off).
- [x] Pest feature test: POST to the download endpoint writes a `files` row with `type=export, purpose=pricing`, returns the CSV with the expected MyPricing headers per `docs/catalog-schema.md#output-column-map`, and updates `last_exported_price` on every inventory row.
- [x] Pest feature test: cancelling (no download) leaves `last_exported_price` unchanged but `calculated_price` updated.
- [x] Pest feature test: download failure (simulated via storage fake throwing) leaves `last_exported_price` unchanged.
- [x] `composer test` passes.

## Implementation notes

- Recompute runs against ALL inventory, not the filtered view — this is core to the doc's contract. Use a dedicated service (`App\Pricing\RecomputeService`) that phase 10 may already expose; if not, this task implements it as a thin wrapper around the algorithm.
- For larger inventories the recompute can take seconds. If it ever exceeds ~3 seconds in practice, queue it as a job and have the page poll for completion before opening the modal — but ship the synchronous version first; only add the queue once it's actually slow.
- The CSV generation should stream rather than buffer-then-send: `Symfony\Component\HttpFoundation\StreamedResponse` keeps memory predictable for large inventories.
- The preview-modal table is itself paginated — for 100k+ inventory rows, even the "changed only" diff can be large. Reuse `MfTable` inside the dialog with server-side pagination scoped to the diff query.
- The `last_exported_price` update happens in a single UPDATE within the same transaction as the `files` row insert — either both succeed or both roll back. This avoids the modal "cancel after recompute" race where a partial update could leave `last_exported_price` ahead of the actual download.
- Update `docs/catalog-schema.md` to document the new `last_exported_price` column (the inventory.md doc explicitly says "I'll add this to catalog-schema.md when we commit"). Keep the addition minimal: one row in the inventory table description + a note under §Pricing export.
- After Clear-overrides bulk in `60-006`, the override-count indicator refetches; the same partial reload should also refresh the page-prop `last_exported_price`-derived state if any (no-op in practice — call out in code).

## Out of scope

- The pricing algorithm itself (phase 10).
- The MyPricing CSV reverse-import (also phase 10 / `docs/catalog-schema.md#mypricing-import`).
- File-history page (Settings → File history is phase 50).
- Real-time progress percentage during recompute (synchronous spinner is fine).
- Email / push notifications when an export completes — out of scope per `docs/saas-design.md` ("No outbound email").
