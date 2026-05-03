---
id: "60-001"
title: "Orders index page — table, filters, sort, default 90-day window"
status: pending
phase: "60-orders-pages"
size: L
depends_on:
  - "phase:00-foundation"
  - "phase:20-orders"
  - "phase:30-components"
references:
  - docs/ux/orders-table.md#layout
  - docs/ux/orders-table.md#interactions
  - docs/ux/orders-table.md#data
  - docs/ux/orders-table.md#mobile-layout
  - docs/ux/orders-table.md#states
  - docs/ux/ux-patterns.md#lists--tables
  - docs/ux/ux-patterns.md#responsive-behavior
  - docs/order-schema.md#orders
  - docs/order-schema.md#status
---

## Goal

Build the `/orders` Inertia page: a server-paginated, sortable, filterable list of every imported order. This is the main daily-operations surface for the seller — every other order workflow (printing slips, jumping to TCGPlayer, importing fresh batches) is launched from here. This task ships the read view; the per-row + bulk packing-slip actions are wired in `60-003`, the import modal is `60-002`.

## Acceptance criteria

- [ ] Route `GET /orders` registered behind the standard auth middleware, named `orders.index`, rendered via Inertia (`Orders/Index.vue`).
- [ ] Wayfinder typed route helper for `orders.index` is regenerated.
- [ ] Controller action returns a Laravel paginator JSON shape compatible with PrimeVue DataTable `lazy` mode (rows + `totalRecords` + page metadata) — see `docs/ux/ux-patterns.md#pagination`.
- [ ] Page renders an `MfTable` (PrimeVue DataTable, `lazy` mode) with the columns specified in `docs/ux/orders-table.md#table`: selection checkbox, Order #, Date, Buyer, Items, Total, Status pill, actions.
- [ ] Order # uses `MfMonospaceId`; Total uses `MfMoney`; Date formatted `MMM D, YYYY` via `useDate`; Status renders via `MfStatusPill` per `docs/ux/ux-patterns.md#status--state`.
- [ ] Default sort: `order_date` desc.
- [ ] Server-side sort supports the columns marked sortable in the spec (`tcgplayer_order_number`, `order_date`, `buyer_name`, `item_count`, `total_amount`, `tcgplayer_status`).
- [ ] Filter panel above the table (`MfFilterPanel`) with: Status multi-select (options sourced from `DISTINCT tcgplayer_status` at request time), Date range (`MfDatePicker`, default last 90 days).
- [ ] Status filter options regenerate per request — a new TCGPlayer status string appears automatically without code changes (verified by feature test).
- [ ] Active filters render as removable chips above the table; "Clear all filters" button appears when ≥1 filter is active.
- [ ] Page rows are **not** clickable; only explicit action icons in the actions column trigger navigation/behavior. Action icons themselves are stubbed in this task (rendered, no click handlers — wired in `60-003`).
- [ ] Mobile (`< 768px`) renders the stacked-card layout from `docs/ux/orders-table.md#mobile-layout` via the `mobile-row` slot. Filter panel becomes a full-screen drawer triggered by a header filter button.
- [ ] Empty states implemented: "no orders ever" with import CTA (CTA stub for `60-002`), "filters return zero rows" with Clear filters.
- [ ] Pest feature test: authenticated GET `/orders` returns 200, renders the Inertia page name `Orders/Index`, and seeded orders appear in the JSON response (assert pagination shape).
- [ ] Pest feature test: filter by `status=Canceled` narrows results to canceled orders only (seed at least one of each status).
- [ ] Pest feature test: sort by `order_date` asc reorders results (assert first-row order number).
- [ ] Pest feature test: date-range filter (`from`/`to` query params) excludes orders outside the range.
- [ ] `composer test` passes.

## Implementation notes

- Use the `MfTable` wrapper from phase 30 — never call PrimeVue DataTable directly in page code.
- The status pill's color logic (`Completed - Paid` + tracking → green, no tracking → amber, `Canceled` → red, other → neutral) is centralized in `MfStatusPill` per `docs/ux/ux-patterns.md#status--state`. The page passes the raw `tcgplayer_status` and `tracking_number` to it.
- The 90-day default is applied **only** when no `from`/`to` query params are present. Once the user touches the date range, URL state takes over.
- URL-driven table state (pagination, sort, filter serialization) is shared with the Catalog and Inventory pages — implement the composable in `60-008` if it doesn't already exist; this task can either build a thin one inline or wait for `60-008`. Prefer building the page first and letting `60-008` refactor common state into a composable.
- Status filter "options" endpoint: simplest path is a `meta.statuses` array in the controller's Inertia response (selected via `DISTINCT tcgplayer_status FROM orders ORDER BY 1`). Do not cache; this query is cheap and the dataset is small.
- `item_count` and `total_amount` may be derived via subquery / `withCount` / aggregated columns — match whatever phase 20 settled on. If they aren't already columns, add them as derived selects in the controller, not new migrations.
- Selection state and bulk action bar: render the empty action bar shell now (visible when ≥1 selected, with a "Select all N matching" link). The bulk action button itself is wired in `60-003`.

## Out of scope

- Import modal and importer wiring (that's `60-002`).
- Per-row "Print packing slip" and bulk "Print packing slips" action handlers (that's `60-003`).
- The actual packing-slip render / PDF (phase 70).
- "Open in TCGPlayer" external link icon — also wired in `60-003` since it's part of the per-row actions pair.
- Pagination + filter URL-state composable extraction (that's `60-008`); inline implementation is acceptable for this task.
- Any in-app order detail page — explicitly does not exist per `docs/ux/orders-table.md#purpose`.
