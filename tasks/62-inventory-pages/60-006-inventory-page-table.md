---
id: "60-006"
title: "Inventory page — required-filter table with inline qty/override edits + per-row + bulk actions"
status: complete
phase: "62-inventory-pages"
size: L
depends_on:
  - "phase:00-foundation"
  - "phase:10-catalog"
  - "phase:30-components"
references:
  - docs/ux/inventory.md#layout
  - docs/ux/inventory.md#interactions
  - docs/ux/inventory.md#data
  - docs/ux/inventory.md#mobile-layout
  - docs/ux/inventory.md#states
  - docs/ux/ux-patterns.md#lists--tables
  - docs/catalog-schema.md#inventory
---

## Goal

Build the `/inventory` Inertia page: a deliberately narrow, filter-required, server-paginated table — one row per `inventory` record. Supports inline-editing Qty and Override price, per-row Reset/Remove actions, and bulk Clear-overrides / Mark-out-of-stock. Includes the chained Product → Set → Condition required-filter contract, the "12 overrides active" counter, and the stale-data indicator. The Export Pricing modal flow is `60-007`.

## Acceptance criteria

- [x] Route `GET /inventory` registered behind auth middleware, named `inventory.index`, rendered via Inertia (`Inventory/Index.vue`). Wayfinder helper regenerated.
- [x] **Required-filter contract**: the table renders no rows (and no headers) until Product, Set (≥1), and Condition (≥1) are all selected. The empty-filters state shows: *"Pick a product, set, and condition to view inventory."* in an outlined placeholder zone.
- [x] Filter panel (`MfFilterPanel`) above the table:
  - Product — single-select dropdown, required.
  - Set — multi-select chained to Product, required (≥1).
  - Condition — multi-select, required (≥1).
  - Has override — toggle, optional.
  - In stock — toggle, default off (zero-qty rows shown by default per `docs/ux/inventory.md#why-show-zero-qty`).
- [x] When all three required filters are set, controller returns paginated rows with columns: Card Name, Number, Market, Low, Calculated, Override, Qty.
- [x] Default sort: card name asc. Server-side sort supported on all listed columns.
- [x] Money columns render via `MfMoney`; null money values render as `—` per `docs/ux/ux-patterns.md#money`.
- [x] **Inline edit — Qty**: clicking a Qty cell renders an `MfQtyInput` in place. Enter or blur saves; Escape cancels. PATCH endpoint updates `inventory.quantity` for the row.
- [x] **Inline edit — Override**: clicking an Override cell renders an `MfMoneyInput` pre-filled with the current override (or empty if null). Saves on Enter / blur; Escape cancels. Empty input → `override_price = null`.
- [x] Inline-edit save semantics implemented per `docs/ux/inventory.md#save-semantics-inline-edits`:
  - Skip no-ops (no save fires when the cell value didn't change).
  - Coalesce in-flight saves to the same cell via `AbortController`.
  - Last-write-wins; cell renders server-confirmed value.
  - Independent cells edit in parallel (per-cell save state).
  - No blunt blur debounce.
- [x] Per-row actions (right edge):
  - Reset to calculated (visible only when `override_price IS NOT NULL`) — `MfConfirmDialog` ("Reset to calculated price?"); sets `override_price = null`.
  - Remove from inventory — always visible — `MfConfirmDialog` ("Remove from inventory?"); sets `quantity = 0` and `override_price = null`. **Soft only** — never hard-deletes the inventory row.
- [x] Bulk actions (action bar visible when ≥1 row selected):
  - Clear overrides — confirm "Clear overrides on N rows?"; sets `override_price = null` for selected.
  - Mark out of stock — confirm "Set quantity to 0 on N rows?"; sets `quantity = 0`. Preserves `override_price` (different from per-row Remove — call this out in the confirm body text).
- [x] "12 overrides active" indicator in the header is clickable and toggles the **Has override** filter. The count refetches after bulk Clear-overrides so it stays in sync.
- [x] Stale-data indicator inline next to the Export button: e.g. "Magic prices are 5 days old". Reads `products.priced_at`; amber when any product's value is null or > 3 days old. Same shape as the Catalog stale indicator.
- [x] Inline-edit error state: cell border red, error tooltip on hover; value reverts to pre-edit per `docs/ux/inventory.md#states`.
- [x] Mobile (`< 768px`) renders the card-row layout from `docs/ux/inventory.md#mobile-layout` via the `mobile-row` slot. Override and Qty cells expose inline edit on tap. Bulk action bar sticky at top of card list. Required-filter empty state is unchanged on mobile.
- [x] URL state: required filters, optional toggles, sort, and pagination all serialize to query string. A bookmarked URL with all three required filters lands directly in the table without flashing the empty state.
- [x] Pest feature test: authenticated GET `/inventory` with no filters returns 200 and renders the empty-filters Inertia state (no rows in props).
- [x] Pest feature test: GET `/inventory?product=X&sets=Y&conditions=Z` returns rows for matching `inventory` records.
- [x] Pest feature test: PATCH on a single inventory row updates `quantity` correctly; same row PATCH with empty override_price clears it to null.
- [x] Pest feature test: bulk-action POST clears overrides on the selected IDs.
- [x] Pest feature test: per-row Remove sets quantity and override_price both to null/0; the inventory row still exists (asserts it's not hard-deleted).
- [x] Pest feature test: sort by `quantity` desc orders results.
- [x] `composer test` passes.

## Implementation notes

- The required-filter empty state is rendered server-side: when filters are incomplete, the controller returns no rows and a `meta.filters_complete = false` flag. The page conditionally renders the placeholder zone vs the table on this flag — this avoids flashing the table headers before filters are picked.
- Inline edits use Inertia partial reloads scoped to the row, not full-page refreshes. Each cell's save calls a small REST-ish endpoint (e.g. `PATCH /inventory/{id}` with `{quantity?, override_price?}`). The server returns the updated row; the page replaces the row in local state.
- The `AbortController` coalescing logic is fiddly enough to deserve a `useInlineCellSave()` composable. Keep it generic so the Catalog page (or future pages) can reuse it if needed.
- Bulk endpoints accept either an array of inventory IDs or a "select all matching" filter signature (current filter query string) — same pattern as `60-003`'s bulk print. Cap bulk operations at 1000 rows server-side; reject with a clear error if exceeded.
- The "12 overrides active" count is a small extra query: `SELECT COUNT(*) FROM inventory WHERE override_price IS NOT NULL` — added to page meta. Refetched via Inertia partial reload after any save that could change it.
- Money is always cents in the DB; `MfMoneyInput` handles cents↔dollars conversion at the boundary per `docs/ux/ux-patterns.md#input-types`.

## Out of scope

- The Export Pricing recompute → preview → CSV flow (that's `60-007`).
- The `last_exported_price` column migration (added in `60-007`, since that's the only consumer).
- Hard-deleting inventory rows — explicitly not exposed per `docs/ux/inventory.md#per-row-actions`.
- Periodic cleanup of soft-removed (qty=0 with overrides) rows — flagged as "consider later" in the doc.
- "First export" banner — that lives inside the Export Pricing preview modal in `60-007`.
- Pricing algorithm itself (phase 10).
