---
id: "30-009"
title: "Build MfFormField + MfMoneyInput + MfQtyInput + MfDatePicker"
status: complete
phase: "30-components"
size: M
depends_on: ["30-002"]
references:
  - docs/ux/components.md#forms--inputs
  - docs/ux/ux-patterns.md#forms
  - docs/ux/ux-patterns.md#input-types
  - docs/saas-design.md
---

## Goal

Form inputs follow a single visual pattern: label + the input + inline error from Inertia's `errors` prop + optional helper text. `MfFormField` is the wrapper that enforces this. The three specialized input wrappers (`MfMoneyInput`, `MfQtyInput`, `MfDatePicker`) bake in non-default behavior (cents↔dollars conversion, large touch targets, ISO date format) so call sites don't repeat boilerplate.

## Acceptance criteria

- [x] `resources/js/components/MfFormField.vue` exists with props:
  - `label: string`
  - `name: string` — error key, looked up against `usePage().props.errors[name]`
  - `required?: boolean` — if true, shows a `*` after the label
  - `help?: string` — optional helper text below the input (rendered when no error is present)
- [x] Default slot renders the actual input. Wrapper outputs:
  - `<label for="{{name}}">{{label}}</label>` with required asterisk
  - the slot
  - inline error in red beneath the input when `errors[name]` is non-empty
  - helper text in muted color when no error exists and `help` is set
- [x] `resources/js/components/MfMoneyInput.vue` exists. Wraps PrimeVue `InputNumber` with:
  - `mode="currency" currency="USD" locale="en-US" :minFractionDigits="2" :maxFractionDigits="2"`
  - `v-model` binds to **cents** (integer) — internal getter divides by 100 to display dollars, internal setter multiplies by 100 to store cents.
  - Props: `modelValue: number | null`, `min?: number`, `max?: number`, `nullable?: boolean` (when true, empty input emits `null` instead of `0`).
- [x] `resources/js/components/MfQtyInput.vue` exists. Wraps PrimeVue `InputNumber` with:
  - Integer mode (`:useGrouping="false"`, no decimals)
  - `:showButtons="true"` and `buttonLayout="horizontal"` (or stacked) — the +/- buttons must render at minimum 44 × 44px tap target on mobile (verify with computed CSS or explicit `min-h-[44px] min-w-[44px]` on the +/- buttons).
  - Props: `modelValue: number`, `min?: number = 0`, `max?: number`.
- [x] `resources/js/components/MfDatePicker.vue` exists. Wraps PrimeVue `DatePicker` (formerly `Calendar` in v3, `DatePicker` in v4):
  - `dateFormat="yy-mm-dd"` (PrimeVue's format syntax for `YYYY-MM-DD`)
  - Single-date mode by default; range mode when `range` prop is true (`selectionMode="range"`).
  - `v-model` is an ISO string (single) or `[from, to]` ISO strings (range).
- [x] All four components support dark mode (no hardcoded colors; PrimeVue tokens + Tailwind utilities only).
- [x] Demo route OR Vue Test Utils tests:
  - `MfFormField` shows the error message when `errors[name]` is set; hides helper text when error is present.
  - `MfMoneyInput` with `modelValue=1234` (cents) displays `$12.34`; user typing `$50.25` emits `5025`.
  - `MfQtyInput` +/- buttons are at least 44px tall.
  - `MfDatePicker` with range mode emits `[from, to]` ISO strings.
- [x] `composer test` passes.

## Implementation notes

- Cents↔dollars conversion in `MfMoneyInput` is the highest-stakes piece in this task. Off-by-one or rounding errors here ripple into every order and pricing page. Use `Math.round(value * 100)` going in and `value / 100` going out — never floats with explicit `.toFixed`.
- The PrimeVue v4 `DatePicker` component is the renamed `Calendar` from v3. If the project pinned an earlier major, adapt accordingly.
- `MfFormField`'s error display: read `usePage().props.errors` directly so any input inside the slot inherits the error binding without prop drilling.
- The label for/htmlFor relationship: pass the `name` as the input's `id` attribute via the slot — document this as a usage convention in a comment at the top of `MfFormField.vue`.

## Out of scope

- File input wrapper (`30-013` — MfFileDropzone).
- Per-page form composition (phase 50/60).
- Server-side validation rules (form requests live in Laravel, not here).
- A wrapper for plain text inputs — bare `<InputText>` inside `<MfFormField>` is the convention; no `MfTextInput` exists.
