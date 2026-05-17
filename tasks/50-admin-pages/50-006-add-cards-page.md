---
id: "50-006"
title: "Add Cards page at `/add-cards` — scoped bulk-entry workflow"
status: complete
phase: "50-admin-pages"
size: L
depends_on: ["50-001", "phase:10-catalog", "phase:30-components"]
references:
  - docs/ux/add-cards.md#purpose
  - docs/ux/add-cards.md#layout
  - docs/ux/add-cards.md#interactions
  - docs/ux/add-cards.md#data
  - docs/ux/add-cards.md#states
  - docs/ux/add-cards.md#mobile-specific-notes
  - docs/catalog-schema.md
---

## Goal

Mobile-first bulk acquisition surface. Operator picks a (Product, Set, Condition) scope, scrolls an alphabetical card list filtered to that scope, and increments quantities with `MfQtyInput` +/- buttons. Tapping Save **adds** entered counts to existing inventory (additive — never replace). Designed for the couch with a phone in hand after pre-sorting a physical pile.

## Acceptance criteria

- [x] Route `GET /add-cards` registered under auth middleware, renders an Inertia page using `AdminLayout` from `50-001`. Page header: title `"Add Cards"`, no primary header action.
- [x] Top of page (scrolls away — **not** sticky): three single-select scope selectors:
  - **Product** — Magic / Lorcana TCG / Flesh & Blood TCG.
  - **Set** — chained to Product; lists all sets within the chosen product alphabetically.
  - **Condition** — single-select, one of the 11 TCGPlayer condition strings per `catalog-schema.md §Condition vocabulary`.
- [x] Card list renders only after all three selectors are set. Until then, render placeholder: `"Pick a product, set, and condition to add cards."`
- [x] When scope is set with zero matching cards: `"No cards in {Set} match {Condition}. Try a different condition."`
- [x] When scope yields cards, render an alphabetical scrollable list (no pagination — full result loaded at once). Each row:
  - **Left**: card name on top line; `#{Number}` muted small text below.
  - **Right**: `MfQtyInput` (− / integer / +). Default 0.
- [x] Rows with qty > 0 render with a subtle green-tinted background and a checkmark glyph next to the qty.
- [x] **Fixed-position** full-width Save button at the bottom of the viewport (not sticky-within-container — always visible while scrolling). Label is dynamic:
  - All qty = 0 → label `"Save"`, button **disabled**.
  - ≥1 qty > 0 → label `"Save N cards"` where N = sum of all entered quantities.
- [x] Card-list scroll area has bottom padding equal to the Save button's height so the last row never sits beneath it.
- [x] On Save: server upserts `inventory` rows for each entry where qty > 0 — find by `card_id` (or insert), set `quantity = existing_quantity + entered_qty` (additive). `override_price`, `calculated_price`, `last_exported_price` left untouched. Entries with qty = 0 are skipped silently.
- [x] After successful save: toast `"Added N cards to {Set} ({Condition})."`, all qty inputs reset to 0, scope selectors stay set.
- [x] Save button is disabled while a save is in flight (prevents double-submit).
- [x] **Re-scoping mid-session** with pending entries (any qty > 0): auto-save the pending entries first, toast `"Saved before switching."`, then load the new scope. If auto-save fails: cancel the switch, render an `MfErrorBanner` at the top, leave the scope selector unchanged.
- [x] Re-scoping with no pending entries (all qty = 0): silent switch, no save call.
- [x] +/- button behavior: `+` increments by 1; `−` decrements by 1, floor 0; tapping the integer opens a numeric keypad for direct entry. Long-press does **not** auto-repeat.
- [x] Pull-to-refresh disabled on the page (best-effort via `overscroll-behavior: contain` or platform-appropriate CSS).
- [x] Mobile: tap targets ≥ 44 × 44 px; no horizontal scroll on a 375 px viewport; numeric keypad does not obscure the fixed Save button.
- [x] Pest feature test `tests/Feature/Admin/AddCardsTest.php` covers:
  - Unauthenticated visit redirects to `/login`.
  - Authenticated visit returns 200 and renders the placeholder when no scope is selected.
  - Scoped query: requesting cards for a (set, condition) pair returns the alphabetical list.
  - Save endpoint: posting entries with qty > 0 increments existing inventory rows additively (assert `quantity = old + new`); creates inventory rows that didn't exist before; entries with qty = 0 are ignored (no row created, no error).
  - Save endpoint validation: rejects negative qty values with 422.
  - Save response includes the total count of cards saved (used by the success toast).
- [x] `composer test` passes.

## Implementation notes

- The Add Cards UX spec is built around the "scoped pile" workflow (pre-sorted by set + condition, increment in place). The user prompt also mentioned "search TCGPlayer, manual add, bulk CSV import" — those flows are NOT in `add-cards.md` and are **out of scope** for this task. See spec gap note below.
- `cards`, `sets`, `products`, and `inventory` tables come from phase 10 (`phase:10-catalog` dep covers them). This task only reads/writes via the existing models.
- The scoped query joins `cards` → `sets` → `products` and filters by `(product_id, set_id, condition)`. A typical result is 50–500 rows; load in full per `add-cards.md §Data`.
- Use `MfQtyInput` from phase 30 for the per-row quantity inputs.
- Use `MfErrorBanner` from phase 30 for the auto-save-failure case.
- Save endpoint should be idempotent in the sense that re-submitting the same entries adds again — there is no "saved entry" deduplication, by design (this is the "no undo" trade per `add-cards.md §Things to consider`).
- The numeric-keypad input on mobile is achieved via `<input inputmode="numeric" pattern="[0-9]*">` semantics inside `MfQtyInput`; verify that wrapper exposes those attributes.
- Pending qty inputs live only in browser memory — no draft persistence in v1 (deferred per `add-cards.md §Things to consider`).

## Spec gap

The user's task brief described Add Cards as `"search TCGPlayer, manual add, bulk CSV import"`, but `docs/ux/add-cards.md` describes a different workflow: scoped pile entry against catalog cards already known to the system. The doc-driven spec is what's implemented above. If the search/manual/CSV flows are needed, they require a UX doc update first — flag during build, do not invent UX.

## Out of scope

- TCGPlayer search to add unknown cards (not in the UX spec — would require new doc work).
- Manual "add a card not in catalog" form (not in the UX spec).
- Bulk CSV import on this page (catalog-side imports live on the Catalog page in phase 60; CSV-driven inventory updates may be added later).
- Editing existing inventory rows (Inventory page, phase 60).
- Pricing — Add Cards explicitly never touches money (no overrides, no calculated_price changes).
- Undo / "last save: undo" toast (deferred per `add-cards.md §Things to consider`).
- Draft persistence in localStorage (deferred per `add-cards.md §Things to consider`).
- Virtual scrolling for very large sets (deferred).
- The admin layout / top nav (`50-001`).
