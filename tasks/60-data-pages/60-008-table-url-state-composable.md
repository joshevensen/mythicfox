---
id: "60-008"
title: "Shared composable for URL-driven table state (pagination, sort, filters, chips)"
status: pending
phase: "60-data-pages"
size: M
depends_on:
  - "60-001"
  - "60-004"
  - "60-006"
  - "phase:30-components"
references:
  - docs/ux/ux-patterns.md#url-driven-state
  - docs/ux/ux-patterns.md#filtering
  - docs/ux/ux-patterns.md#pagination
  - docs/ux/ux-patterns.md#sorting
---

## Goal

The Orders, Catalog, and Inventory pages all serialize pagination, sort, and filter state to the URL using the same conventions per `docs/ux/ux-patterns.md#url-driven-state`. After the three page tasks land their inline implementations, refactor the shared mechanics into a single Vue composable so all three pages converge on one source of truth — refresh-safe, back-button-correct, and bookmarkable. Also includes the active-filter-chip rendering pattern shared across pages.

## Acceptance criteria

- [ ] `useTableState(options)` composable exists in `resources/js/composables/useTableState.ts` with:
  - Reactive state for `page`, `perPage`, `sort` (`{ field, dir }`), and a `filters` object whose shape is provided per call (typed via generic).
  - Reads initial state from the current URL on mount; falls back to defaults from `options`.
  - On any state change, calls `router.get(currentRoute, serializedQuery, { preserveState: true, preserveScroll: true, replace: true })` per the doc.
  - Multi-value filters serialize comma-separated; `dir` is `asc`|`desc`; empty filters omitted from URL.
  - Exposes `clearFilters()` and `removeFilter(key, value?)` helpers.
- [ ] `MfFilterChips` (or extend the existing chip rendering in phase 30) shows one removable chip per active filter; clicking the X calls `removeFilter`. "Clear all filters" button appears when `filters` is non-empty.
- [ ] Orders, Catalog, and Inventory pages refactored to use `useTableState` — the inline serialization from `60-001`, `60-004`, `60-006` is removed in favor of the composable.
- [ ] Back/forward browser navigation restores state correctly on all three pages.
- [ ] Refresh on a filtered + sorted + paginated page restores the exact same view (verified by Playwright/Cypress-style flow if available, otherwise asserted via Inertia page-prop snapshots in feature tests).
- [ ] Dashboard quick-action shortcuts (e.g. `/orders?import=1`) still work — query params not owned by `useTableState` are preserved on subsequent state changes.
- [ ] Required-filter contract on Inventory still holds: `useTableState` does not render a table until the page-specific "filters complete" predicate is satisfied. The composable exposes a `filtersComplete` computed that pages can opt into.
- [ ] Pest / Vitest unit tests for `useTableState`:
  - Round-trips a complex state (page, sort, multi-value filters) through URL serialization and back.
  - Removing a single value from a multi-value filter leaves the rest intact.
  - Clearing all filters resets to defaults but preserves non-table query params (e.g. `import=1`).
- [ ] Existing feature tests on Orders / Catalog / Inventory still pass with no behavior change.
- [ ] `composer test` passes.

## Implementation notes

- The composable is the consolidation point for what `60-001`, `60-004`, and `60-006` shipped inline. Prefer a non-disruptive refactor — same URL shape, same controller query params, just one shared implementation.
- Use `useUrlSearchParams` from VueUse for the read side or roll a small custom parser; either is fine. Whatever you pick, expose a single `serialize(state) → query` and `deserialize(query) → state` pair so future pages can opt in cheaply.
- Don't use `router.replace` from Vue Router — Inertia is the navigation system here. Use `router.get(...)` from `@inertiajs/vue3` with `replace: true`.
- The chip rendering belongs to whichever Mf* component already covers the filter panel; if there's no `MfFilterChips` yet in phase 30, add one here in `resources/js/components/ui/MfFilterChips.vue`.
- The "Has override" toggle on Inventory is a boolean filter — the composable should serialize booleans as `1`/absent (not `true`/`false` strings) for shorter URLs.
- Per-page sort defaults stay configurable: orders defaults to `order_date desc`, catalog defaults to `card_name asc`, inventory defaults to `card_name asc`. Pass via `options.defaultSort`.

## Out of scope

- Saved-views UI — the doc explicitly says "bookmarks are the saved-views mechanism." No custom saved-views feature.
- Server-side query building beyond what already exists in the page controllers — this task is purely client-side.
- Multi-column sort — single-column server-side sort only per `docs/ux/ux-patterns.md#sorting`.
- Persisting state to localStorage as a fallback — URL is the only source of truth.
