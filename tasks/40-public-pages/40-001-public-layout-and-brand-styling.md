---
id: "40-001"
title: "Public layout shell + brand styling for public-facing pages"
status: complete
phase: "40-public-pages"
size: M
depends_on: ["phase:00-foundation", "phase:30-components"]
references:
  - docs/ux/public-homepage.md#layout
  - docs/ux/public-homepage.md#seo
  - docs/ux/login.md#layout
  - docs/ux/ux-patterns.md#brand-colors
  - docs/saas-design.md#stack--deployment
---

## Goal

The public-facing surfaces (homepage and login) share a brand shell distinct from the admin app shell. There is no top nav, no auth chrome — just centered brand layout, dark-mode-aware brand tokens, and the favicon / `<head>` metadata that every public route needs. Build the layout once so the homepage and login pages plug into it without re-deciding wrappers, head tags, or color tokens.

## Acceptance criteria

- [x] An Inertia layout component `resources/js/layouts/PublicLayout.vue` exists and renders: a default slot for page content, the shared footer (delivered by `40-004`, stub it for now if that task hasn't shipped — see notes), and applies the brand-color CSS custom properties from `ux-patterns.md` (`--mf-orange`, `--mf-teal`, `--mf-brown` swap on `.dark`).
- [x] The layout sets the `<title>` and `<meta name="description">` via Inertia's `Head` component, with per-page override props (`title`, `description`).
- [x] The favicon link tags listed in `public-homepage.md §SEO` (favicon-32, favicon-16, favicon.ico, apple-touch-icon, manifest, theme-color `#EA5A1F`) render in the document head on every page using this layout. Wire from `resources/views/app.blade.php` if that's the simplest spot, otherwise via `@inertiaHead`.
- [x] Dark mode is the default per `saas-design.md` — the `.dark` class is applied to `<html>` on initial render of public pages without flicker. (The vue-starter-kit appearance toggle already handles admin; ensure the public layout respects it.)
- [x] PrimeVue is configured with the `MythicFoxPreset` from `ux-patterns.md §Brand colors` so that `bg-mf-orange`, `text-mf-teal`, `border-mf-brown` tailwind aliases work and PrimeVue components inherit the orange primary.
- [x] A Pest feature test (`tests/Feature/Public/PublicLayoutTest.php`) hits a stub route that renders the layout and asserts: response 200, `<title>` contains the passed title, the favicon link tags are present, `.dark` class on `<html>`.
- [x] `composer test` passes.

## Implementation notes

- This task does NOT build the homepage or login pages themselves — only the shell. The homepage (`40-002`) and login (`40-003`) tasks consume this layout.
- If `40-004` hasn't landed yet, render an inline footer placeholder (`<footer />`) that `40-004` will replace with the proper component.
- Brand color CSS variables go in a global stylesheet (`resources/css/app.css` or similar) — one `:root` block, one `html.dark` block, exactly as written in `ux-patterns.md`.
- The PrimeVue preset configuration likely already exists from phase 30; if not, add it here. Pin a major version per `ux-patterns.md §Things to consider`.
- Use Wayfinder typed route helpers throughout — no hardcoded URL strings (per `AGENTS.md`).
- The structured-data JSON-LD `Organization` block from `public-homepage.md §SEO` is page-specific (homepage only) — do NOT put it in the shared layout.

## Out of scope

- The homepage hero/about/feature/buyers-say sections (that's `40-002`).
- The login form (that's `40-003`).
- The shared footer's actual markup (that's `40-004`).
- `/sitemap.xml` and `/robots.txt` (that's `40-004`).
- The admin layout / authenticated app shell (that's `50-001`).
