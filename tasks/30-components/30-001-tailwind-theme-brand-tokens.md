---
id: "30-001"
title: "Wire brand color tokens into Tailwind theme (light + dark)"
status: complete
phase: "30-components"
size: S
depends_on: ["phase:00-foundation"]
references:
  - docs/ux/ux-patterns.md#brand-colors
  - docs/ux/ux-patterns.md#stack
  - docs/ux/components.md#decisions
---

## Goal

Every later `Mf*` component reads brand colors via Tailwind utility classes (`bg-mf-orange`, `text-mf-teal`, `border-mf-brown`) backed by CSS custom properties that swap on `.dark`. Defining the tokens once, here, is the single source of truth that the PrimeVue preset (`30-002`) and every component (`30-005` onward) will reference. Get this wrong and brand color drifts across the app.

## Acceptance criteria

- [x] CSS custom properties defined in `resources/css/app.css` (or a dedicated `resources/css/tokens.css` imported from `app.css`) for `--mf-orange`, `--mf-teal`, `--mf-brown` with the light values from the table in `docs/ux/ux-patterns.md#brand-colors`. The light hex values: `#EA5A1F`, `#2E899B`, `#5C2D0E`.
- [x] `html.dark` overrides for the same three tokens with the dark hex values: `#FF7B45`, `#5BB5C9`, `#D9B896`.
- [x] Tailwind v4 theme aliases expose the tokens as utilities (`mf-orange`, `mf-teal`, `mf-brown`) — registered via `@theme` directive in `app.css` per Tailwind v4 conventions, not a `tailwind.config.ts` file (project is on Tailwind 4 — see `package.json`).
- [x] Verify in a temporary `resources/js/pages/Welcome.vue` swatch (or the existing one) that `bg-mf-orange`, `text-mf-teal`, `border-mf-brown` render the correct colors in both light and dark modes. Remove the swatch when done — it's a verification step, not committed code.
- [x] Body text and surface backgrounds continue to follow Tailwind `slate`/`gray` neutrals; this task does NOT change neutral palette.
- [x] Semantic status colors (`emerald-500/400`, `amber-500/400`, `red-500/400`) remain the standard Tailwind palette — referenced from `MfStatusPill` later, no aliasing needed here.
- [x] `composer test` passes.

## Implementation notes

- Tailwind 4 declares colors via `@theme { --color-mf-orange: var(--mf-orange); }` in CSS, not a JS config object. Keep all theme tokens together.
- The existing starter kit may already toggle `.dark` on `<html>` via the appearance composable — do not rewire that mechanism in this task; just ensure the tokens respond to it. Dark-mode setup is `30-003`.
- Do NOT introduce a separate Tailwind config file unless you have to — Tailwind 4's CSS-first config is the project convention.
- Typography scale and spacing: keep Tailwind defaults. The doc references brand colors, not custom typographic scales — don't invent ones.

## Out of scope

- Configuring the PrimeVue preset to pick up these tokens (`30-002`).
- Building the dark-mode toggle UI / persistence (`30-003`).
- Any `Mf*` component implementation (`30-005` onward).
