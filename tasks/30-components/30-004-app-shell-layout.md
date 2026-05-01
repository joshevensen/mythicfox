---
id: "30-004"
title: "Build MfAppLayout root layout (top nav + page container slots)"
status: pending
phase: "30-components"
size: M
depends_on: ["30-002", "30-003"]
references:
  - docs/ux/ux-patterns.md#navigation
  - docs/ux/components.md#layout--navigation
  - docs/ux/dashboard.md
---

## Goal

Every admin page mounts inside the same shell: top nav + page container with consistent padding/max-width. Establishing the shell now (with `MfTopNav` and `MfPageHeader` slot points wired but using placeholder content if the components themselves aren't yet built) lets phase 50/60 pages drop in without each one re-deriving padding, max-width, or nav placement. The starter's existing `AppLayout.vue` is sidebar-based — replace it with a top-nav-based layout that matches `docs/ux/ux-patterns.md#top-nav`.

## Acceptance criteria

- [ ] `resources/js/layouts/MfAppLayout.vue` exists and exports a default Vue component that:
  - Renders `<MfTopNav />` (placeholder OK if the real component lands in `30-005`; this layout depends on the slot, not the implementation).
  - Renders `<MfPageContainer>` (max-width wrapper) with the default slot, wrapping page content.
  - Provides a `<MfToast />` mount point and `<MfConfirmDialog />` mount point near the root so `useToast()` / `useConfirm()` work app-wide. Placeholders OK; real components in `30-012`.
- [ ] The layout sets the `<html>`-level dark-mode behavior from `30-003`; do not duplicate the mechanism.
- [ ] `MfPageContainer.vue` lives at `resources/js/components/MfPageContainer.vue` and provides the standard page padding (e.g. `px-4 sm:px-6 lg:px-8 py-6`) and max-width (e.g. `max-w-7xl mx-auto`). Specific values: pick from existing starter conventions or Tailwind defaults — record the choice in the commit message so future tweaks have one place to look.
- [ ] At least one existing Inertia page (e.g. `pages/Dashboard.vue`) is migrated to use `MfAppLayout` via Inertia's persistent layout pattern (`defineOptions({ layout: MfAppLayout })` or `<script setup>` `defineLayout` equivalent).
- [ ] Pest browser test: `visit('/dashboard')` (authenticated) asserts the top-nav slot is rendered and the main content region has the `MfPageContainer` class hooks (`max-w-7xl` or whatever the chosen scoped class is).
- [ ] The old sidebar-based `AppLayout.vue` and its sub-components are NOT yet removed — leave for now to avoid breaking other pages mid-phase. A follow-up task (or the per-page migration tasks in phase 50/60) handles cleanup.
- [ ] `composer test` passes.

## Implementation notes

- Inertia v3 supports persistent layouts. The starter's existing pattern (`<script setup>` with a `layout` export) is the convention.
- TopNav and toast/confirm placeholders can be a single empty `<div data-mf-slot="topnav" />` so the layout shape is locked even before the real component exists. When `30-005` ships `MfTopNav`, swap the placeholder for the real import.
- Don't introduce a sidebar slot. The doc's nav model is top-nav only.
- Mobile: at `< 768px` the layout still renders the same `<MfTopNav />` — the top-nav component itself owns the hamburger collapse (per `30-005`). The container wrapper does not need responsive logic beyond Tailwind padding utilities.

## Out of scope

- Building `MfTopNav` content (`30-005`).
- Building `MfPageHeader` (`30-006`).
- Replacing the existing sidebar layout app-wide (per-page migrations in phases 40/50/60).
- The public-facing layout (no top nav, just the public homepage — phase 40).
