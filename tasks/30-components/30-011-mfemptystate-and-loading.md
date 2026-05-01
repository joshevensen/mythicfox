---
id: "30-011"
title: "Build MfEmptyState (and confirm MfTable's skeleton loading)"
status: pending
phase: "30-components"
size: S
depends_on: ["30-007"]
references:
  - docs/ux/components.md#mfemptystate
  - docs/ux/ux-patterns.md#empty--loading--error-states
---

## Goal

Two empty cases exist across tables and pages: "no data exists" (with an actionable CTA — e.g. "No orders yet — import your first order CSVs") and "filters return zero rows" (with a "Clear filters" button). `MfEmptyState` covers both via a single component with title + body + optional CTA. Loading skeletons are owned by `MfTable` itself (per `30-007`); this task verifies they look right and don't need a standalone wrapper.

## Acceptance criteria

- [ ] `resources/js/components/MfEmptyState.vue` exists with props per `docs/ux/components.md#mfemptystate`:
  - `title: string`
  - `body?: string`
  - `ctaLabel?: string`
  - `ctaRoute?: string` — when present alongside `ctaLabel`, renders an Inertia `<Link>` styled as a PrimeVue `Button`.
- [ ] Layout: vertically centered, optionally with a muted icon above the title (PrimeIcons `pi pi-inbox` or similar for tables; pages can override via a `icon` prop — add it).
- [ ] Add `icon?: string` prop (PrimeIcons name without the `pi pi-` prefix). Component prepends `pi pi-`. Default: none.
- [ ] When used inside `MfTable` (default empty state), it renders centered within the table-body area, not the whole page.
- [ ] Dark-mode safe: text colors via Tailwind `slate` neutrals or PrimeVue Aura tokens.
- [ ] Verify (visually or via test): the skeleton loading rows in `MfTable` (built in `30-007`) render at default page size during a lazy fetch — no work needed here unless something looks off; if so, fix in `MfTable` and reference the fix in this task's commit.
- [ ] Demo route OR Vue Test Utils test:
  - Mount `<MfEmptyState title="No orders yet" body="Import your first order CSVs" cta-label="Import" cta-route="/orders?import=1" />`.
  - Assert title, body, and CTA link all render with correct text and href.
  - Mount with only `title` prop; assert body and CTA are absent.
- [ ] `composer test` passes.

## Implementation notes

- The CTA renders as an Inertia `<Link>` wrapping a PrimeVue `<Button>`. Use Wayfinder typed routes when the destination route exists; if it's a query-string-bearing URL (e.g. `?import=1`), a string href is acceptable.
- The `icon` prop is added here even though `docs/ux/components.md#mfemptystate` doesn't list it — the UX-patterns empty-state guidance reads better with an icon, and adding it here avoids a refactor when the orders page calls for one. Note this minor extension in the commit message.

## Out of scope

- A "skeleton" component for non-table loading (use PrimeVue `Skeleton` directly where needed).
- Page-level error boundaries (separate concern).
- Specific empty-state copy per page (phase 50/60 owns).
