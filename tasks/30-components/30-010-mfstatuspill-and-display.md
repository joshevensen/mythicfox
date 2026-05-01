---
id: "30-010"
title: "Build MfStatusPill + MfMoney + MfDate + MfMonospaceId + MfCardIdentity"
status: pending
phase: "30-components"
size: M
depends_on: ["30-002"]
references:
  - docs/ux/components.md#display
  - docs/ux/ux-patterns.md#display
  - docs/ux/ux-patterns.md#status--state
  - docs/order-schema.md
  - docs/catalog-schema.md
---

## Goal

Display-only components. They have no PrimeVue primitive underneath — they're presentation atoms that encode formatting decisions (cents → `$1,234.56`, ISO → `Nov 14, 2025`, status string → green/amber/red pill) so individual page templates don't reimplement them. Group them in one task because they're small and share a use-the-same-helpers pattern.

## Acceptance criteria

- [ ] `resources/js/composables/useMoney.ts` exists. Exports `useMoney()` returning `formatCents(cents: number | null): string` that produces `$1,234.56` for non-null integers and `'—'` (em-dash) for null. Two decimals, comma thousands, USD, en-US.
- [ ] `resources/js/composables/useDate.ts` exists. Exports `useDate()` returning `formatDate(value: string, format?: 'date'|'datetime'): string`:
  - `'date'` → `MMM D, YYYY` (e.g. `Nov 14, 2025`)
  - `'datetime'` → `MMM D, YYYY h:mma` (e.g. `Nov 14, 2025 1:41pm`)
- [ ] `resources/js/components/MfMoney.vue` exists. Props: `cents: number | null`, `align?: 'left' | 'right' = 'right'`. Renders the formatted string inside a `<span>` with text-alignment matching the `align` prop. Uses `useMoney()`.
- [ ] `resources/js/components/MfDate.vue` exists. Props: `value: string` (ISO), `format?: 'date' | 'datetime' = 'date'`. Renders the formatted string. Uses `useDate()`.
- [ ] `resources/js/components/MfMonospaceId.vue` exists. Props: `value: string | number`. Renders the value in `font-mono` with no truncation. Used for `tcgplayer_order_number` and TCGplayer Ids.
- [ ] `resources/js/components/MfStatusPill.vue` exists. Props: `status: string`, `trackingNumber: string | null`. Implements the table from `docs/ux/ux-patterns.md#status--state`:
  - `Completed - Paid` + tracking populated → emerald (green) pill, white text, includes a small `pi pi-check` icon for color-blind accessibility.
  - `Completed - Paid` + tracking null → amber pill, white text, `pi pi-clock` icon.
  - `Canceled` → red pill, white text, `pi pi-times` icon.
  - any other / unknown → neutral gray pill, dark text, no icon.
- [ ] Status pill colors use Tailwind `emerald-500/400`, `amber-500/400`, `red-500/400`, `slate-200/700` (light/dark) — NOT brand colors. Per the doc: "Brand colors should never be used to encode meaning."
- [ ] `resources/js/components/MfCardIdentity.vue` exists. Props: `card: { name, number, set, condition?, rarity? }` (or accepts an `orderItem` shape with the same identity fields). Renders the two-line stack:
  - Top line: `{Product Name} · #{Number} · {Set Name}` (separator `·` with `text-slate-400` muted color).
  - Bottom line: `{Condition}` left, `{Rarity}` right, both in muted text.
  - `compact?: boolean` — when true, single-line mode (`{Name} #{Number}`) for tight contexts.
- [ ] Demo route OR Vue Test Utils tests:
  - `MfMoney` with `cents=123456` renders `$1,234.56`.
  - `MfMoney` with `cents=null` renders `—`.
  - `MfDate` with ISO string formats correctly in both `date` and `datetime` modes.
  - `MfStatusPill` produces green pill for `'Completed - Paid'` + tracking, amber pill for `'Completed - Paid'` + null tracking, red for `'Canceled'`, gray for an unknown value.
  - `MfCardIdentity` renders both lines in default mode and one line in compact.
- [ ] `composer test` passes.

## Implementation notes

- Date formatting: use `Intl.DateTimeFormat` with `en-US` and explicit `month: 'short'`, `day: 'numeric'`, `year: 'numeric'`. Avoid pulling in `date-fns` or `dayjs` for this — the spec is narrow and `Intl` covers it.
- Money formatting: `Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100)`.
- `MfCardIdentity`'s "accepts two shapes" caveat from `docs/ux/components.md#things-to-consider` — define a normalization helper inside the component that maps both `card` and `orderItem` shapes to a `{ name, number, set, condition, rarity }` internal object. Document the contract.
- Pill colors: write them as Tailwind classes (e.g. `bg-emerald-500 dark:bg-emerald-400`) — do not introduce variants via component props beyond what the status logic dictates.

## Out of scope

- `MfMoneyInput` for editing money — that's `30-009`.
- A `MfTimezone`-aware datetime — single user, single timezone (server's), no need.
- Internationalization of the money/date locales — locked to `en-US` per `docs/ux/ux-patterns.md`.
