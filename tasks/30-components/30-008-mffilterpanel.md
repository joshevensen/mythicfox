---
id: "30-008"
title: "Build MfFilterPanel + MfFilterChip + MfSearchInput"
status: pending
phase: "30-components"
size: M
depends_on: ["30-007"]
references:
  - docs/ux/components.md#mffilterpanel
  - docs/ux/components.md#mffilterchip
  - docs/ux/components.md#mfsearchinput
  - docs/ux/ux-patterns.md#filtering
---

## Goal

Filters are the second-most-used UI pattern after the table itself. `MfFilterPanel` is the collapsible filter panel that lives above an `MfTable`, renders typed filter controls, manages the URL state, and shows removable active-filter chips. Centralizing the panel + chip + debounced search in one task keeps the API consistent across Orders, Catalog, and Inventory.

## Acceptance criteria

- [ ] `resources/js/components/MfFilterPanel.vue` exists with prop:
  - `filters: FilterDef[]` where `FilterDef = { kind: 'text' | 'enum' | 'range' | 'date' | 'boolean'; key: string; label: string; options?: { value: string; label: string }[] }`.
- [ ] Per filter kind, the panel renders:
  - `text` — `<MfSearchInput>` (debounced 300ms).
  - `enum` — PrimeVue `MultiSelect` showing chips for selected values.
  - `range` — two PrimeVue `InputNumber` inputs (min / max) side-by-side.
  - `date` — `<MfDatePicker range>` (`30-010` provides; placeholder OK if order shifts).
  - `boolean` — PrimeVue `ToggleSwitch` or `InputSwitch`.
- [ ] Active-filter chips render above the controls. Each chip shows `{label}: {value}` and an X icon to remove that one filter. Implemented via `<MfFilterChip>` in `resources/js/components/MfFilterChip.vue`.
- [ ] "Clear all filters" button appears next to the chips when ≥1 filter is active.
- [ ] All filter changes update the URL via Inertia `router.get(url, { preserveState: true })`. Multi-value enum filters serialize as comma-separated values (e.g. `?product=Magic,Lorcana`).
- [ ] Panel is collapsible (PrimeVue `Panel` with `:collapsed` and a toggle). Default state: expanded on desktop, collapsed on tablet, full-screen drawer on mobile (per `docs/ux/ux-patterns.md#responsive-behavior`).
- [ ] On mobile (`< 768px`), the panel renders as a PrimeVue `Drawer` triggered by a "Filters" button rendered by the consuming page (the panel exposes a `v-model:open` API for the drawer state so the page can wire its own trigger button).
- [ ] `resources/js/components/MfSearchInput.vue` exists. Wraps PrimeVue `IconField` + `InputText` (or just `InputText`) with a 300ms debounce on the `update:modelValue` emit. Used by the panel for `text`-kind filters and standalone where a page wants a single search box.
- [ ] Demo route OR Vue Test Utils test:
  - Mount the panel with one text filter and one enum filter.
  - Type into the search; assert the URL update fires once after 300ms (not on every keystroke).
  - Select two enum values; assert the URL contains the comma-joined value and an active chip appears for each.
  - Click an X on a chip; assert that filter is removed from the URL.
- [ ] `composer test` passes.

## Implementation notes

- The `MultiSelect` in PrimeVue 4 has a `chip` mode that renders selected items as inline pills. Use it.
- Debounce: use `@vueuse/core`'s `useDebounceFn` (already in `package.json`) — don't roll a custom debounce.
- Chip rendering uses PrimeVue `Chip` with a `removable` prop; if styling needs alignment with brand, override only via Aura theme tokens — not by writing one-off CSS.
- The "active-filter" computed value reads the current Inertia page query string (`usePage().props.ziggy.location` or whatever the project uses) and renders one chip per non-empty filter key.

## Out of scope

- Specific filter sets per page (phase 50/60).
- Saving filter combos as named views — the doc's stance is "bookmarks are the saved-views mechanism."
- Search within MultiSelect option lists (PrimeVue handles natively when option count is large).
