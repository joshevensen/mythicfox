---
id: "30-012"
title: "Build MfConfirmDialog + MfToast + MfErrorBanner feedback components"
status: complete
phase: "30-components"
size: M
depends_on: ["30-002", "30-004"]
references:
  - docs/ux/components.md#feedback
  - docs/ux/ux-patterns.md#confirmation
  - docs/ux/ux-patterns.md#toasts
---

## Goal

Three transient/feedback surfaces: confirmation dialogs (used before destructive actions), toast notifications (success/error/info after async operations), and inline error banners (above tables/forms when something fails but the page should keep the existing data visible). All three wrap PrimeVue services to enforce project conventions (verb-specific buttons, top-right toast positioning, retry-or-dismiss banner pattern).

## Acceptance criteria

- [x] `resources/js/components/MfConfirmDialog.vue` exists. Mounts the PrimeVue `<ConfirmDialog />` once at the layout root (already placeholder-mounted in `30-004`). Apply Mythic Fox-specific defaults: cancel button on the left, action button on the right, action button uses PrimeVue's `severity="danger"` styling when `destructive: true`.
- [x] `resources/js/composables/useMfConfirm.ts` exists. Exports `useMfConfirm()` which wraps PrimeVue's `useConfirm()` with the API:
  ```ts
  confirm({
    title: string,
    body: string,
    verb: string,           // 'Delete' | 'Reset' | 'Clear' etc. — never 'OK'
    destructive?: boolean,  // applies danger styling
    onConfirm: () => void,
    onCancel?: () => void,
  })
  ```
- [x] The confirm composable refuses (TypeScript-level or runtime warning) verbs `'OK'`, `'Yes'`, `'Confirm'` — they violate the convention; force callers to be specific.
- [x] `resources/js/components/MfToast.vue` exists. Mounts the PrimeVue `<Toast />` once at the layout root with `position="top-right"`.
- [x] `resources/js/composables/useMfToast.ts` exists. Wraps PrimeVue's `useToast()` with shorthands:
  - `.success(msg: string, title?: string)` — green styling, auto-dismiss 4s.
  - `.error(msg: string, title?: string)` — red styling, no auto-dismiss (stays until user dismisses) per `docs/ux/ux-patterns.md#toasts`.
  - `.info(msg: string, title?: string)` — neutral styling, auto-dismiss 4s.
- [x] `resources/js/components/MfErrorBanner.vue` exists with props:
  - `title?: string`
  - `message: string` (required)
  - `onRetry?: () => void` — when present, renders a "Retry" button.
  - When no `onRetry` is provided, render a "Dismiss" button instead that emits `@dismiss` for the parent to handle.
- [x] Banner styling: red border-left, slightly tinted red background, error icon (`pi pi-exclamation-triangle`). Dark-mode safe.
- [x] Both `MfToast` and `MfConfirmDialog` are mounted in `MfAppLayout` (replace the placeholder slots from `30-004` with real components).
- [x] Demo route OR Vue Test Utils tests:
  - `useMfConfirm({ verb: 'OK', ... })` warns or throws (project preference; either is acceptable).
  - `useMfToast().success('Saved')` triggers a top-right toast with auto-dismiss timing.
  - `MfErrorBanner` with `onRetry` shows a Retry button; without it, shows Dismiss and emits the event.
- [x] `composer test` passes.

## Implementation notes

- PrimeVue 4 services (`ConfirmationService`, `ToastService`) are registered globally in `30-002`. The composables here just import `useConfirm`/`useToast` from `'primevue/useconfirm'` / `'primevue/usetoast'` and add the project conventions.
- Verb enforcement: simplest implementation is a `console.warn` when a banned verb is passed plus a TypeScript union type that excludes them at compile time (`type Verb = string & { __verb: never }` is overkill; prefer a literal union like `'Delete' | 'Reset' | 'Clear' | 'Discard' | 'Remove'` with an escape-hatch `string` overload — record the choice in a code comment).
- Error toast persistence: PrimeVue's `Toast` accepts `life: undefined` (or `0`) to mean "no auto-dismiss." Use that for `.error()`.

## Out of scope

- Stacked / queued toasts beyond PrimeVue's defaults.
- Custom toast styling beyond Aura semantic tokens.
- Banner variants for warning / info states (only error per the doc).
- Modal dialogs for non-confirmation use (PrimeVue `Dialog` directly when a page needs one — no `MfModal` wrapper specced).
