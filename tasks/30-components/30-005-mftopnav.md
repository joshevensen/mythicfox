---
id: "30-005"
title: "Build MfTopNav (sticky top navigation with mobile hamburger drawer)"
status: pending
phase: "30-components"
size: M
depends_on: ["30-004"]
references:
  - docs/ux/components.md#mftopnav
  - docs/ux/ux-patterns.md#top-nav
  - docs/ux/ux-patterns.md#navigation
---

## Goal

The top nav is on every authenticated page and is the only navigation surface (no sidebar). It must be sticky, render the brand logo + section links + user menu, highlight the active route, and collapse to a hamburger drawer below 768px. Per `docs/ux/components.md`, the component takes no props — section list is hardcoded.

## Acceptance criteria

- [ ] `resources/js/components/MfTopNav.vue` exists.
- [ ] Renders, left-to-right on desktop (≥ 768px):
  - Mythic Fox logo (links to `/dashboard` via Wayfinder typed route).
  - Section links: **Dashboard · Orders · Catalog · Inventory · Settings** — using Inertia `<Link>` and Wayfinder route helpers (no hardcoded URL strings; per `AGENTS.md` Wayfinder rules).
  - User menu on the right (avatar/name → dropdown with "Log out") — wraps PrimeVue `Menu` or PrimeVue `OverlayPanel`. Logs out via Inertia POST to the Fortify logout route.
- [ ] Active route highlighting: the link matching the current Inertia `usePage().url` gets a visible active state (e.g. `bg-mf-orange/10 text-mf-orange`). The match is prefix-based (`/orders/*` → Orders link active).
- [ ] Mobile (`< 768px`): horizontal nav collapses to a hamburger button on the left. Tapping opens a full-screen drawer (PrimeVue `Drawer` / Sidebar component) with the section list and the user menu's contents. Logo stays centered/top-visible.
- [ ] Sticky positioning: `sticky top-0 z-40` (or equivalent) so it stays pinned during scroll.
- [ ] Dark mode: surface and text colors come from PrimeVue Aura semantic tokens or Tailwind `slate` neutrals — no hardcoded hex.
- [ ] No props. Component is fully self-contained.
- [ ] Demo route OR Pest+Vue Test Utils test: a route `/dev/components/topnav` (gated to `local` env, or a Pest browser test against an existing page that includes the layout) verifies the nav renders all five section links and the active state matches the current route. Either approach satisfies the criterion.
- [ ] `composer test` passes.

## Implementation notes

- Section links are hardcoded as a TS `const SECTIONS = [{ label: 'Dashboard', route: dashboard.url() }, ...]`. Use Wayfinder-generated route helpers; if a section's route doesn't exist yet (e.g. `orders`, `catalog`, `inventory` aren't built until phases 50/60), use a placeholder URL string like `'/orders'` and add a TODO comment to swap in the typed helper when the route lands. Don't block this task on later route definitions.
- The user-menu avatar can be a PrimeIcons `pi pi-user` placeholder; real avatar handling isn't a v1 concern.
- Settings page link points to `/settings/profile` (or whatever the existing settings route is).
- Use `MfAppLayout` (`30-004`) as the mount point — replace the placeholder slot with the real `<MfTopNav />`.

## Out of scope

- Building any of the destination pages (Orders, Catalog, Inventory, Settings beyond what already exists).
- User profile picture upload.
- Notification badge / unread indicator on any nav link.
