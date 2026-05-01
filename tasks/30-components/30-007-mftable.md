---
id: "30-007"
title: "Build MfTable (lazy DataTable wrapper with URL state, mobile-row slot)"
status: pending
phase: "30-components"
size: L
depends_on: ["30-002"]
references:
  - docs/ux/components.md#mftable
  - docs/ux/ux-patterns.md#lists--tables
  - docs/ux/ux-patterns.md#mobile-row-slot-pattern
  - docs/ux/orders-table.md
  - docs/ux/catalog.md
  - docs/ux/inventory.md
---

## Goal

The workhorse of the app. Orders, Catalog, Inventory, and File History all use the same table pattern: PrimeVue DataTable in `lazy` mode, server-side pagination/sort/filter, default page size 50, URL-driven state, skeleton loading, mobile responsive switch to card rows. Encapsulating this in one wrapper means every per-page table task in phases 50/60 only has to declare columns + endpoint, not re-derive lazy-mode wiring or URL serialization.

## Acceptance criteria

- [ ] `resources/js/components/MfTable.vue` exists with props per `docs/ux/components.md#mftable`:
  - `endpoint: string` — the Inertia route or API URL the table fetches from.
  - `columns: ColumnDef[]` where `ColumnDef = { key: string; label: string; sortable?: boolean; align?: 'left'|'right'|'center'; formatter?: (val: any, row: any) => string; slot?: string }`.
  - `defaultSort?: { column: string; dir: 'asc'|'desc' }`.
  - `selectable?: boolean` — enables a leading checkbox column and master-checkbox.
  - `expandable?: boolean` — enables per-row expand toggle (used by Catalog).
  - `rowAction?: 'navigate' | 'modal' | 'none'` — row-click behavior.
- [ ] Server contract: requests query string `?page=N&per_page=N&sort=col&dir=asc|desc&<filterKey>=value,...`. Response shape: `{ data: Row[], meta: { total: number, current_page: number, per_page: number } }` (Laravel paginator `->paginate()` response shape).
- [ ] Default page size: 50. Page-size options: `[25, 50, 100, 200]`. Pagination control bottom-aligned, "Showing N–M of T" text, prev/next/jump-to-page.
- [ ] URL serialization: every interaction updates the query string via Inertia `router.get(url, { preserveState: true, preserveScroll: true, only: [<inertia partial keys>] })`. On mount, the table reads its initial state from the URL (so refresh restores filters).
- [ ] Sort: single-column, click header to cycle `unsorted → asc → desc → unsorted`. Sort indicator visible.
- [ ] Slots:
  - `filters` — content goes above the table (intended for `<MfFilterPanel>`).
  - `bulk-actions` — sticky action bar above the table when ≥1 row is selected; shows selected count + slot contents. Includes "Select all N matching" link when the page-level master is checked.
  - `empty` — overrides the default empty state.
  - `expand-row` — sub-row contents for expandable tables (slot props: `{ row }`).
  - `mobile-row` — per-row card layout for screens `< 768px`; slot props: `{ row, selected, toggleSelect, expanded, toggleExpand }`. When this slot is absent, the table falls back to a horizontal-scroll layout on mobile.
  - per-column cell slots named after the column key (e.g. `cell-status`).
- [ ] Loading: PrimeVue skeleton rows during lazy fetches; full-table spinner only on initial mount.
- [ ] Default empty state: renders `<MfEmptyState>` (`30-009`) with a generic "No results" title; pages override via the `empty` slot.
- [ ] Error state: when fetch fails, render `<MfErrorBanner>` (`30-012`) above the table with a "Retry" button; previously-loaded rows stay visible if any.
- [ ] Mobile breakpoint switch happens at `768px` (Tailwind `md:`). Below that:
  - If `mobile-row` slot is provided: hide table headers and column borders, render each row through the slot.
  - Otherwise: keep the table layout with `overflow-x-auto`.
- [ ] Demo route OR Vue Test Utils test mounts a small example table with mock data (3 rows, 2 columns) and asserts:
  - Initial render shows all 3 rows.
  - Clicking a sortable header issues a request with the expected `sort=` and `dir=` params.
  - Changing page size updates `per_page=` in the URL.
  - With `selectable`, checking a row triggers the bulk-actions slot.
- [ ] `composer test` passes.

## Implementation notes

- Built on PrimeVue `DataTable` with `:lazy="true"`, `:totalRecords="..."`, `@page`, `@sort`, `@filter` events. Don't reinvent the table primitive.
- Use the project's existing Inertia router (`@inertiajs/vue3`) for partial reloads, not `fetch`. Inertia handles auth, CSRF, and prop hydration; raw fetches don't.
- TypeScript: define `ColumnDef`, `FilterDef`, and `MfTableState` interfaces in `resources/js/components/MfTable.types.ts` (or co-located). Export them so per-page code can import them.
- URL state debouncing: text-search filters debounce upstream (in `MfFilterPanel`), not in the table itself. The table just consumes whatever the URL says.
- "Select all N matching" mechanic: when the master checkbox is checked, the bulk-actions slot receives an extra prop indicating page-level selection; clicking the "Select all N matching" link sets a flag the page can read for cross-page bulk actions.
- Skeleton-row count: 5 rows during lazy fetches feels right; make it configurable via prop only if a real page needs it.

## Out of scope

- Specific filter rendering (`30-008` — MfFilterPanel).
- Specific empty-state styling (`30-009`).
- Per-page column definitions (phase 50/60).
- File-history table rendering (phase 70).
