# Mythic Fox Games — Pages & UX Design

Index of UX docs. Each page of the app has its own focused doc under [ux/](ux/). Cross-cutting decisions (table patterns, money/date display, status pills, top nav, etc.) live in shared docs at the same level.

## Cross-cutting

- **[ux/ux-patterns.md](ux/ux-patterns.md)** — list, form, display, navigation conventions. Single source of truth for `MfTable` defaults, filter UX, status-pill colors, money/date formatting, top nav sections.
- **[ux/components.md](ux/components.md)** — `Mf*` PrimeVue wrappers and project-specific reusable components.

## Per-page

| Page | Route | Doc |
|---|---|---|
| Public homepage | `/` | [ux/public-homepage.md](ux/public-homepage.md) |
| Login | `/login` | [ux/login.md](ux/login.md) |
| Dashboard | `/dashboard` | [ux/dashboard.md](ux/dashboard.md) |
| Orders Table | `/orders` | [ux/orders-table.md](ux/orders-table.md) |
| Catalog | `/catalog` | [ux/catalog.md](ux/catalog.md) |
| Inventory | `/inventory` | [ux/inventory.md](ux/inventory.md) |
| Add Cards | `/add-cards` | [ux/add-cards.md](ux/add-cards.md) |
| Settings | `/settings` | [ux/settings.md](ux/settings.md) |

There is no order detail page — clicking the TCGPlayer-link icon on an order row opens that order in the TCGPlayer Seller Portal. See [ux/orders-table.md](ux/orders-table.md).

## Notes

- **Mobile-first throughout the app.** Every page must function on a 375px-wide screen. Add Cards and the public homepage have layouts specifically tuned for phone use; other pages use responsive table patterns (card-row layout on phones, horizontal scroll on tablets, full table on desktop). See [ux/ux-patterns.md §Responsive behavior](ux/ux-patterns.md).
- Admin lives behind `/login` (Fortify). The public homepage is the only unauthenticated route besides `/login` itself.
- All page-level open questions have been resolved. The cross-doc `gaps.md` has been retired.
- Brand colors, icon library, and testing posture are settled in [ux/ux-patterns.md](ux/ux-patterns.md) and [ux/components.md](ux/components.md).

---

## Things to consider

- **This file is a TOC; don't let it grow back.** Page-specific detail belongs in the per-page docs, not here. When adding a new page, add a single row to the table above and create a fresh doc under `ux/`.
- **Stale link risk.** If a per-page doc gets renamed or deleted, the table above goes stale. Keep this in sync as part of any per-page doc change.
- **Mobile-first is enforced via [ux/ux-patterns.md](ux/ux-patterns.md).** Per-page docs only call out *deviations* — desktop-only features need explicit justification.
