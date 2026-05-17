---
id: "50-002"
title: "Dashboard page at `/dashboard` — greeting + quick-action tiles"
status: complete
phase: "50-admin-pages"
size: M
depends_on: ["50-001", "phase:30-components"]
references:
  - docs/ux/dashboard.md#layout
  - docs/ux/dashboard.md#page-header
  - docs/ux/dashboard.md#quick-actions
  - docs/ux/dashboard.md#interactions
  - docs/ux/dashboard.md#data
---

## Goal

Post-login home. Intentionally minimal v1: a "Welcome back, {name}" greeting, a 2×2 grid of quick-action tiles (Add Cards / Import Orders / Catalog / Export Pricing), and a single muted "More dashboards coming soon…" footer line. No widgets, no aggregates — that's deferred until real workflow surfaces concrete questions.

## Acceptance criteria

- [x] Route `GET /dashboard` registered under auth middleware, renders an Inertia page using `AdminLayout` from `50-001`.
- [x] Page header displays the greeting `"Welcome back, {first name from users.name}"` and subtitle `"Mythic Fox Games"`. No primary action button on the header — actions are in the tile grid.
- [x] Quick-actions section renders a 2×2 grid of `MfPageContainer`-styled tiles, each with an icon, label, and short description:
  - **+ Add Cards** → `/add-cards` ("Add new cards to inventory")
  - **⬆ Import Orders** → `/orders?import=1` ("Print packing slips and import a fresh batch")
  - **📃 Catalog** → `/catalog`
  - **💲 Export Pricing** → `/inventory?export=1`
- [x] Tiles are tap-targets ≥ 44 × 44px, hover-accented on desktop, single column on mobile.
- [x] Below the tiles, render the muted line `"More dashboards coming soon as your workflow takes shape."`
- [x] First-name extraction: `users.name` may be a full name; render only the first whitespace-delimited token. If `users.name` is blank, fall back to the email's local part.
- [x] Pest feature test `tests/Feature/Admin/DashboardTest.php` covers:
  - Anonymous request redirects to `/login`.
  - Authenticated request returns 200.
  - Response contains the greeting with the user's first name.
  - All four tile destinations are present in the rendered HTML (use the route names, asserted via Wayfinder helpers in the test).
- [x] `composer test` passes.

## Implementation notes

- The two destinations carrying query params (`?import=1`, `?export=1`) are read by the destination pages on mount to auto-open modals — that wiring lives in phase 60 (Orders / Inventory pages). This task just emits the URLs.
- The tile destinations `/add-cards`, `/catalog`, `/inventory`, `/orders` may not all be live routes yet (phase 60 owns the data tables; `50-006` owns Add Cards). The tile links should still render as the typed routes via Wayfinder — broken links during the build window are acceptable. Note any cross-phase ordering concerns in the commit message.
- Use Wayfinder for all tile destinations — no hardcoded paths.
- PrimeIcons for the tile glyphs (`pi pi-plus`, `pi pi-upload`, `pi pi-list`, `pi pi-dollar` or similar).
- The "first name" derivation is presentation-layer only — don't add a `first_name` column to `users`.

## Out of scope

- Any dashboard widgets (open orders count, inventory value, revenue charts) — explicitly deferred per `dashboard.md §Why minimal for v1`.
- The Orders / Inventory pages that handle the `?import=1` / `?export=1` query params (phase 60).
- The Add Cards page (`50-006`).
- The Catalog page (phase 60).
- The admin layout / top nav (that's `50-001`).
