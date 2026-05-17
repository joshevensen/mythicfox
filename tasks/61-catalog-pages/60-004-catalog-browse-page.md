---
id: "60-004"
title: "Catalog browse page — aggregated table with expand-rows + stale-data indicator"
status: complete
phase: "61-catalog-pages"
size: L
depends_on:
  - "phase:00-foundation"
  - "phase:10-catalog"
  - "phase:30-components"
references:
  - docs/ux/catalog.md#layout
  - docs/ux/catalog.md#interactions
  - docs/ux/catalog.md#data
  - docs/ux/catalog.md#mobile-layout
  - docs/ux/catalog.md#states
  - docs/ux/ux-patterns.md#lists--tables
  - docs/catalog-schema.md#cards
  - docs/catalog-schema.md#inventory
---

## Goal

Build the `/catalog` Inertia page: a server-paginated, sortable, filterable browse view of every card the system knows about — including zero-stock cards. Parent rows aggregate by `(set_id, product_name, number)` with a `Total Qty` summed across condition variants; expanding a row reveals one sub-row per condition. Includes the chained Product → Set filter and the "Magic refreshed N days ago" stale-data indicator. The PricingCustomExport upload modal is `60-005`.

## Acceptance criteria

- [x] Route `GET /catalog` registered behind auth middleware, named `catalog.index`, rendered via Inertia (`Catalog/Index.vue`). Wayfinder helper regenerated.
- [x] Controller returns paginated, aggregated parent rows grouped by `(set_id, product_name, number)` with: card name, collector number, set name, rarity, `SUM(inventory.quantity) AS total_qty` (defaulting to 0 when no inventory rows exist).
- [x] Default sort: card name asc. Server-side sort supported on card name, number, set name, rarity, total qty.
- [x] Page renders an `MfTable` with `expandable` enabled. Per-row expand toggle (`▸` chevron); clicking the toggle (or anywhere on a desktop parent row) expands inline.
- [x] Expand row renders a nested table with one row per condition variant: Condition (verbatim string), Quantity (`0` if no `inventory` row), TCGplayer ID (`MfMonospaceId`). Sub-rows sorted by Condition asc.
- [x] Filter panel (`MfFilterPanel`) above the table:
  - Product — single-select dropdown, default "All products".
  - Set — multi-select chained to Product; selections that don't match the new Product are dropped silently with a small toast (*"Removed N set filters not in {Product}"*).
  - In stock — toggle, default off; when on adds a `HAVING SUM(inventory.quantity) > 0` clause to the aggregated query.
- [x] Stale-data indicator beside the upload button: renders one inline string per product (e.g. "Magic refreshed 2 days ago"). Reads from `products.priced_at`. Renders the entry in amber/warning text when any product's `priced_at` is null or older than 3 days.
- [x] Empty states implemented: "no catalog rows ever" with upload CTA (CTA stub for `60-005`), "filters return zero rows" with Clear filters.
- [x] Mobile (`< 768px`) renders the parent-row card layout from `docs/ux/catalog.md#mobile-layout` via the `mobile-row` slot. Tap-anywhere-to-expand. Sub-rows render as a tighter list inside the expanded card.
- [x] Expand-all is **not** offered — explicitly disabled per `docs/ux/catalog.md#things-to-consider`.
- [x] Pest feature test: authenticated GET `/catalog` returns 200, renders Inertia page `Catalog/Index`, paginated parent rows include the seeded card.
- [x] Pest feature test: filtering by Product narrows results; filtering by Set requires a Product to be selected (chained behavior verified).
- [x] Pest feature test: "In stock" toggle excludes cards where every condition variant has zero quantity.
- [x] Pest feature test: sort by `total_qty` desc orders results (assert first row).
- [x] Pest feature test: stale-data indicator data is present in the page props with the correct shape (one entry per product, with `priced_at` and `is_stale` boolean).
- [x] `composer test` passes.

## Implementation notes

- The aggregated parent-row query is the heaviest read in the app — see `docs/ux/catalog.md#things-to-consider`. Build it as a dedicated query class (e.g. `App\Catalog\Queries\BrowseCardsQuery`) so it can be tuned/replaced without touching the controller. Inputs: filters + pagination; output: paginator + meta (the per-product `priced_at` summary).
- Make sure indexes from `docs/ux/catalog.md#indexes-db` are present: `cards (set_id, product_name, number)`, `cards (product_name)`, FK indexes on `cards.set_id`, `sets.product_id`. If phase 10 didn't add them, add them via a migration in this task.
- Stale-data summary: a single small query — `SELECT id, name, priced_at FROM products` — added to the Inertia response under `meta.products_priced_at`. Computing `is_stale` (now − priced_at > 3 days, or null) happens server-side. The page renders the strings.
- For the chained Set filter, the page hits a small `GET /catalog/sets?product=X` endpoint (or includes set lists in initial props keyed by product). Keep the implementation simple — the dataset is small.
- Sub-row sub-table can be a child component (`Catalog/RowExpand.vue`) that takes the parent's `(set_id, product_name, number)` and queries `cards` + `inventory` for variants on demand or receives them eager-loaded with the parent — eager-loading keeps the network noise down on expand.
- Reuse the URL-driven table state composable from `60-008` if it ships before this task; otherwise inline serialization is fine and `60-008` will refactor.

## Out of scope

- The PricingCustomExport upload modal (that's `60-005`).
- Pricing override editing — explicitly **not** on the Catalog page; lives on Inventory (`60-006`).
- A materialized `card_groups` view — only build it if the live aggregated query becomes a measurable bottleneck in production.
- Per-set `priced_at` (the doc flags this as a "consider later" item — leave it as per-product for now).
- Archive-product concept (catalog rows accumulate forever; explicitly out of scope per the doc).
