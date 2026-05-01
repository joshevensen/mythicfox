---
id: "30-002"
title: "Install PrimeVue + PrimeIcons and register Mythic Fox Aura preset"
status: pending
phase: "30-components"
size: M
depends_on: ["30-001"]
references:
  - docs/ux/ux-patterns.md#stack
  - docs/ux/ux-patterns.md#brand-colors
  - docs/ux/components.md#decisions
---

## Goal

Every `Mf*` wrapper extends a PrimeVue primitive. The PrimeVue plugin must be registered on the Inertia/Vue app with an Aura-based theme preset whose primary color slot is pinned to `--mf-orange` (defined in `30-001`). Without this, brand color shows up only in Tailwind utilities and PrimeVue components render in the default Aura blue.

## Acceptance criteria

- [ ] `npm install primevue @primevue/themes primeicons` succeeds; packages appear in `package.json` `dependencies` (not `devDependencies`).
- [ ] `resources/js/app.ts` (or wherever `createInertiaApp` lives) registers PrimeVue via `app.use(PrimeVue, { theme: { preset: MythicFoxPreset, options: { darkModeSelector: '.dark' } } })`.
- [ ] `MythicFoxPreset` defined in `resources/js/lib/primevue-preset.ts` (new file) using `definePreset(Aura, { semantic: { primary: { 500: 'var(--mf-orange)', ... } } })` per the snippet in `docs/ux/ux-patterns.md#brand-colors`. Non-500 shades use Aura's built-in `{orange.NNN}` ramp.
- [ ] PrimeIcons CSS imported once at the entry point: `import 'primeicons/primeicons.css'`.
- [ ] PrimeVue `ConfirmationService` and `ToastService` plugins also registered on the app — required by `useConfirm()` (`30-012` MfConfirmDialog) and `useToast()` (`30-012` MfToast).
- [ ] A throwaway page or route renders a bare PrimeVue `<Button label="Test" />` with the `pi pi-check` icon and the button background matches `--mf-orange` in light mode, `#FF7B45` in dark mode. Remove the verification button when confirmed.
- [ ] `darkModeSelector: '.dark'` is set so PrimeVue's built-in dark mode follows the `<html class="dark">` toggle the starter already uses.
- [ ] No PrimeVue-specific CSS reset clashes with Tailwind base — verify the existing pages still render. If clashes appear, follow the PrimeVue + Tailwind docs (`tailwindcss-primeui` plugin) and document the choice in the commit.
- [ ] `composer test` passes (incl. `npm run lint:check` and `vue-tsc --noEmit`).

## Implementation notes

- Use PrimeVue 4.x. Pin the major version in `package.json` per the warning in `docs/ux/ux-patterns.md#things-to-consider` ("PrimeVue version churn").
- Path alias: the project uses `@/` for `resources/js/` — use it consistently when importing the preset.
- The `@primevue/themes` package exports `Aura` and `definePreset`. Do not bundle a CSS theme file — Aura is CSS-in-JS via the plugin.
- Don't pre-import every PrimeVue component globally. Inertia pages can import what they need; `Mf*` wrappers will import their underlying PrimeVue components locally.
- If `tailwindcss-primeui` is needed for utility-friendly Aura tokens, install it as a dev dep and add it to `app.css` `@plugin` directive.

## Out of scope

- Building any `Mf*` wrapper (`30-005` onward).
- Per-component PrimeVue imports (handled in each wrapper task).
- Dark-mode toggle UI (`30-003`).
