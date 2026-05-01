---
id: "30-006"
title: "Build MfPageHeader (title + breadcrumbs + action button slot)"
status: pending
phase: "30-components"
size: S
depends_on: ["30-004"]
references:
  - docs/ux/components.md#mfpageheader
  - docs/ux/ux-patterns.md#page-headers
  - docs/ux/orders-table.md
---

## Goal

Every admin page has a header band beneath the top nav: title on the left, primary action button(s) on the right, optional breadcrumbs above the title for detail pages. Standardizing this in one component keeps every page's header band visually consistent and removes per-page boilerplate.

## Acceptance criteria

- [ ] `resources/js/components/MfPageHeader.vue` exists with props:
  - `title: string` (required)
  - `breadcrumbs?: { label: string; route?: string }[]` — optional array; renders above the title separated by a chevron icon (`pi pi-chevron-right`). Items with `route` render as Inertia `<Link>`; items without render as plain text (the current page is the last crumb, no link).
- [ ] Default slot renders right-aligned in the same row as the title (use flex layout); intended for primary action buttons (e.g. `<Button label="Import orders" icon="pi pi-upload" />`).
- [ ] Title uses an `<h1>` element with appropriate semantic weight (e.g. `text-2xl font-semibold`). Color: `text-slate-900 dark:text-slate-100` or via PrimeVue Aura's body text token — no hardcoded brand color (the doc reserves `mf-brown` for headings in light mode but only on light surfaces; safer to use neutral here and revisit per-page if needed).
- [ ] Mobile (`< 768px`): action buttons stack below the title rather than crowding the right edge. Use `flex flex-col sm:flex-row sm:items-center sm:justify-between`.
- [ ] Demo route OR Vue Test Utils test mounts the component with a title, two breadcrumb items, and a default-slot button; asserts:
  - The title renders as h1.
  - Both breadcrumb items appear, separator icon between them.
  - The slot button renders to the right of the title on `≥sm` widths.
- [ ] `composer test` passes.

## Implementation notes

- Breadcrumb separator: PrimeIcons `pi pi-chevron-right` with `text-slate-400` for muted appearance.
- Don't wrap the title with `MfPageContainer` — the container is applied at the layout level (`30-004`); the header sits inside the container.
- If a Vue test framework isn't yet added to the project, the demo-route option is acceptable; pick whichever is faster, document the choice in the commit.

## Out of scope

- Adding a description / subtitle slot (not in the spec).
- Search input embedded in the header (page-specific, lives elsewhere).
- The container wrapping the page below the header.
