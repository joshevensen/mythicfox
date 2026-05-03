---
id: "50-001"
title: "Admin layout / authenticated app shell with MfTopNav"
status: complete
phase: "50-admin-pages"
size: M
depends_on: ["phase:00-foundation", "phase:30-components"]
references:
  - docs/ux/ux-patterns.md#top-nav
  - docs/ux/ux-patterns.md#page-headers
  - docs/ux/ux-patterns.md#toasts
  - docs/ux/components.md
  - docs/ux/dashboard.md
  - docs/saas-design.md#auth--users
---

## Goal

Every admin page (Dashboard, Orders, Catalog, Inventory, Add Cards, Settings, plus phase-60 data tables) sits inside the same authenticated shell: sticky `MfTopNav` at the top, `MfPageHeader` band beneath, `MfPageContainer` body wrapper, PrimeVue Toast region for transient feedback. Build the layout once so every later admin task plugs in without re-deciding chrome, auth middleware, or toast configuration.

## Acceptance criteria

- [x] An Inertia layout component `resources/js/layouts/AdminLayout.vue` exists. It renders `<MfTopNav />` (from phase 30), a default slot for page content wrapped in `<MfPageContainer />`, and a global PrimeVue `<Toast />` region positioned top-right per `ux-patterns.md §Toasts`.
- [x] The layout enforces auth: any Inertia route using it requires the authenticated middleware. Unauthenticated visitors are redirected to `/login`.
- [x] `MfTopNav` displays the section links Dashboard / Orders / Catalog / Inventory / Settings, the Mythic Fox logo (left, links to `/dashboard`), and a user menu on the right with the logged-in user's name and a "Log out" item that POSTs to Fortify's logout endpoint and lands on `/login`. ("File history" is NOT a top-nav item — it's a Settings section per `ux-patterns.md`.)
- [x] Mobile (`< 768px`): the horizontal nav collapses to a hamburger menu on the left; tapping it opens a full-screen drawer with the section list and user menu. Logo stays visible center/top.
- [x] Active section is highlighted in the nav based on the current Inertia route.
- [x] Toast region uses `mf-orange` for primary success accents and the semantic emerald/amber/red colors from `ux-patterns.md §Brand colors` for state coloring; auto-dismiss after 4 seconds (errors persist until dismissed).
- [x] Dark mode is the default per `saas-design.md`; no light-mode flicker on initial render.
- [x] Pest feature test `tests/Feature/Admin/AdminLayoutTest.php` covers:
  - Anonymous request to a route using `AdminLayout` (use a stub route or `/dashboard` if `50-002` has shipped) redirects to `/login`.
  - Authenticated request returns 200 and the response payload contains the user's name (proxy for `MfTopNav` rendering).
  - Logout POST destroys the session and redirects to `/login`.
- [x] `composer test` passes.

## Implementation notes

- `MfTopNav`, `MfPageHeader`, `MfPageContainer` are delivered by phase 30. This task wires them into a layout — it does not redefine them.
- Wayfinder typed routes for every nav link — no hardcoded URL strings (per `AGENTS.md`).
- The user menu uses PrimeVue `Menu` or similar; the user's `name` is read from `Auth::user()` and exposed via Inertia shared props.
- The "Log out" action POSTs to Fortify's logout (don't manually `Auth::logout()` from a custom controller).
- `MfPageHeader` is rendered **per-page**, not by the layout itself — pages decide their own title and primary actions. The layout just provides the slot.
- Don't add a footer to the admin shell — admin pages don't have one (footer is public-side only, per `ux-patterns.md §Navigation`).

## Out of scope

- Dashboard page content (that's `50-002`).
- Settings sections (`50-003` / `50-004` / `50-005`).
- Add Cards page (`50-006`).
- Data tables for Orders / Catalog / Inventory (phase 60).
- The `MfTopNav`, `MfPageHeader`, `MfPageContainer` components themselves (phase 30).
- Public-side layout (that's `40-001`).
