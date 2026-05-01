---
id: "40-004"
title: "Public footer + sitemap.xml + robots.txt"
status: pending
phase: "40-public-pages"
size: S
depends_on: ["40-001"]
references:
  - docs/ux/public-homepage.md#footer
  - docs/ux/public-homepage.md#seo
---

## Goal

Ship the small pieces of public-side chrome that aren't a page: the shared footer used by `PublicLayout`, the `/sitemap.xml` route, and `/robots.txt`. Tiny in scope, but each one needs to exist before launch and there's no per-page doc that owns them.

## Acceptance criteria

- [ ] A `PublicFooter.vue` component exists and is rendered by `PublicLayout` (replacing the placeholder from `40-001`). It outputs a single muted line: `© 2025 – {currentYear} Mythic Fox Games  ·  [Admin]`.
  - When `currentYear === 2025` the range collapses to `© 2025 Mythic Fox Games  ·  [Admin]`.
  - Otherwise, the range form is used (e.g. `© 2025 – 2026 Mythic Fox Games  ·  [Admin]`).
  - `{currentYear}` is computed at render time (`new Date().getFullYear()`).
- [ ] The "Admin" link points to the `/login` route via Wayfinder. Discreet styling (muted text color, no button chrome).
- [ ] No social links, no nav, no newsletter signup — exactly the spec from `public-homepage.md §Footer`.
- [ ] Route `GET /sitemap.xml` returns a valid XML sitemap listing only `/` (admin routes excluded). Response `Content-Type: application/xml`.
- [ ] Route `GET /robots.txt` returns `Allow: /` and `Disallow:` entries for `/login`, `/dashboard`, `/orders`, `/catalog`, `/inventory`, `/add-cards`, `/settings`, plus a `Sitemap:` line pointing to the absolute URL of `/sitemap.xml`. Response `Content-Type: text/plain`.
- [ ] Pest feature tests cover:
  - `GET /sitemap.xml` returns 200, content-type XML, body contains the homepage URL.
  - `GET /robots.txt` returns 200, content-type plain, body contains the disallow list and the sitemap line.
  - The footer renders on the homepage (assert presence of the copyright string and the Admin link `href`).
  - Year-collapse logic: when "current year" is 2025 the dash-range is absent (mockable via a small helper or by injecting a time helper).
- [ ] `composer test` passes.

## Implementation notes

- `/sitemap.xml` and `/robots.txt` are best served by simple controllers returning `Response::make($body, 200, [...])` — no need to install a sitemap package for a one-URL site. If a controller feels heavy, a closure route is fine.
- The footer's "Admin" link uses Wayfinder for the `/login` route. No hardcoded `/login` strings.
- Year-collapse: implement in the Vue component (`computed` or inline). Keep the implementation small — a single ternary suffices.
- The disallow list in `robots.txt` should match the route table in `public-homepage.md §Routing model` — keep them in sync if routes get renamed.
- Submitting `/sitemap.xml` to Google Search Console is an operator-manual post-launch step (noted in `public-homepage.md §SEO`); do NOT automate it.

## Out of scope

- The login page itself (that's `40-003`).
- The homepage hero/about/etc. (that's `40-002`).
- OG / Twitter card images (descoped per `public-homepage.md §SEO`).
- Search Console submission (manual operator step).
- Admin-side footer (admin pages don't have one per `ux-patterns.md`).
