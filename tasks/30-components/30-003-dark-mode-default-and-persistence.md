---
id: "30-003"
title: "Make dark mode the default with persistence across sessions"
status: pending
phase: "30-components"
size: S
depends_on: ["30-002"]
references:
  - docs/ux/ux-patterns.md#brand-colors
  - docs/saas-design.md
  - docs/ux/settings.md#section-account
---

## Goal

Per `tasks/README.md` project decisions: "Dark mode: mandatory and the default." The Laravel starter ships with a three-way appearance toggle (light/dark/system). For this app, `dark` is the initial state on first visit and the persisted default; light mode is still selectable from the Settings page (per `docs/ux/settings.md`) but should never be what a fresh browser sees.

## Acceptance criteria

- [ ] On first visit (no persisted preference, no inline pre-hydration script value), `<html class="dark">` is set before first paint — no flash of light mode.
- [ ] The pre-hydration inline script in `resources/views/app.blade.php` reads localStorage for the saved preference; if absent, defaults to `dark` (NOT `system`).
- [ ] The Vue-side `useAppearance` (or equivalent composable in `resources/js/composables/`) defaults to `dark` instead of `system` for new users.
- [ ] The existing `AppearanceTabs.vue` component continues to work — user can flip to light, the choice persists, and reloading honors it.
- [ ] PrimeVue's `darkModeSelector: '.dark'` (set in `30-002`) means PrimeVue components automatically follow.
- [ ] The Tailwind brand-color tokens from `30-001` swap correctly: `bg-mf-orange` shows `#EA5A1F` in light mode and `#FF7B45` in dark mode after toggling.
- [ ] Pest test (or Vitest test if Vue testing is set up): asserts the appearance composable's default value is `'dark'`. If neither test framework is wired for this kind of assertion, a Pest browser test (`visit('/')->assertPresent('html.dark')`) is acceptable.
- [ ] `composer test` passes.

## Implementation notes

- Pest 4 has browser testing (per AGENTS.md "pest-testing" skill). `visit('/login')` and asserting the `<html>` has the `dark` class is a clean acceptance check.
- The starter's `useAppearance` composable lives in `resources/js/composables/useAppearance.ts` (or similar). Update the default constant; do not rewrite the composable.
- Keep the three options (light/dark/system) available in Settings — only the *default* changes. Removing the system option would be a separate UX decision.
- If `system` is the saved preference and the OS reports light, light mode is correct — system means system. The default change only affects users who have NOT yet expressed a preference.

## Out of scope

- Removing the appearance toggle entirely.
- Restyling the toggle UI (Settings page is `phase:50-admin-pages`).
- Building dark variants of pages that don't exist yet (per-page tasks own that).
