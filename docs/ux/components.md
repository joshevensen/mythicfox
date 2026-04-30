# UI Components

Project-specific Vue components, mostly thin wrappers over PrimeVue that bake in the conventions from [ux-patterns.md](ux-patterns.md). Per-page docs reference these by name and assume the conventions hold — they don't redefine behavior here.

**Naming**: components are prefixed `Mf` (Mythic Fox) so they don't collide with PrimeVue (`Button`, `DataTable`) and so they're easy to grep. File location: `resources/js/components/`.

**Rule of thumb**: write a wrapper when (a) we need a default different from PrimeVue's default, (b) we'd repeat boilerplate at every call site, or (c) the component encodes a project-specific concept (status pill, card identity). If the PrimeVue component is fine as-is, just use it.

---

## Layout & navigation

### `<MfTopNav>`
Sticky top nav. Renders the brand logo + section links + user menu. Reads current route to highlight the active section. Per [ux-patterns.md §Top nav](ux-patterns.md).

**Props**: none — fully self-contained.

### `<MfPageHeader>`
Page title + optional primary action button(s) + optional breadcrumbs. Sits beneath the top nav.

**Props**:
- `title: string`
- `breadcrumbs?: { label, route? }[]`
- Default slot: action buttons (right-aligned)

### `<MfPageContainer>`
Standard page padding + max-width wrapper. Wraps every admin page's body so margins are consistent.

---

## Tables & lists

### `<MfTable>`
The workhorse wrapper around PrimeVue DataTable. Bakes in: `lazy` mode, server-side pagination/sort/filter, default page size 50, page-size options [25, 50, 100, 200], URL-driven state, skeleton-row loading, standard empty/error states.

**Props**:
- `endpoint: string` — the Inertia route or API URL the table fetches from
- `columns: ColumnDef[]` — typed column definitions (key, label, sortable, align, formatter, slot)
- `defaultSort?: { column, dir }`
- `selectable?: boolean` — enables row checkboxes + bulk action bar
- `rowAction?: 'navigate' | 'modal' | 'none'` — row click behavior
- Slots: `filters`, `bulk-actions`, `empty`, per-column cell slots

### `<MfFilterPanel>`
Collapsible panel above a table. Renders filter controls and the active-filter chips. Reads/writes URL state.

**Props**:
- `filters: FilterDef[]` — typed filter definitions (kind: text/enum/range/date/boolean, key, label, options for enums)
- Auto-debounced text inputs (300ms)

### `<MfFilterChip>`
Single removable filter chip. Used internally by `MfFilterPanel`.

### `<MfSearchInput>`
Debounced text search input. 300ms debounce. Used by `MfFilterPanel` for text filters and standalone where a page wants a single search box without the full filter panel.

### `<MfEmptyState>`
Centered empty-state component for tables and pages with no data. Title + body + optional CTA button.

**Props**:
- `title: string`
- `body?: string`
- `ctaLabel?: string`, `ctaRoute?: string`

---

## Forms & inputs

### `<MfFormField>`
Label + slot + inline error. Pairs with Inertia's `errors` prop. Every form input goes inside one.

**Props**:
- `label: string`
- `name: string` — error key
- `required?: boolean`
- `help?: string` — optional helper text below the input
- Default slot: the input

### `<MfMoneyInput>`
PrimeVue InputNumber wrapper. Binds to a v-model in **cents** (the storage unit), but displays formatted dollars. Two-decimal currency format, USD, en-US.

**Props**:
- `modelValue: number | null` — cents
- `min?: number`, `max?: number`
- `nullable?: boolean` — if true, empty input emits null instead of 0

### `<MfQtyInput>`
PrimeVue InputNumber wrapper for integer quantities. Min 0, +/- buttons, large tap targets for mobile (Add Cards page).

**Props**:
- `modelValue: number`
- `min?: number = 0`
- `max?: number`

### `<MfDatePicker>`
PrimeVue Calendar wrapper. Single-date or range mode. ISO display format `YYYY-MM-DD`. Used for date filters and any date input.

**Props**:
- `modelValue: string | string[]` — ISO date(s)
- `range?: boolean`

---

## Display

### `<MfMoney>`
Formatted money display. Renders `$1,234.56` for cents, `—` for null. Right-align by default in tables (override with prop). Per [ux-patterns.md §Money](ux-patterns.md).

**Props**:
- `cents: number | null`
- `align?: 'left' | 'right' = 'right'`

### `<MfDate>`
Formatted date / datetime display. `MMM D, YYYY` for dates, `MMM D, YYYY h:mma` for datetimes. Per [ux-patterns.md §Dates](ux-patterns.md).

**Props**:
- `value: string` — ISO date or datetime
- `format?: 'date' | 'datetime' = 'date'`

### `<MfStatusPill>`
Colored pill for TCGPlayer status + tracking state. Per [ux-patterns.md §Status / state](ux-patterns.md). Encapsulates the green / amber / red logic so call sites just pass the order row.

**Props**:
- `status: string` — `tcgplayer_status` value
- `trackingNumber: string | null`

### `<MfCardIdentity>`
Two-line card identity block. `{Product Name} · #{Number} · {Set Name}` on top, `{Condition}` + `{Rarity}` muted beneath.

**Props**:
- `card: Card` (or `orderItem: OrderItem`) — accepts either shape and reads identity fields
- `compact?: boolean` — single-line mode for tight contexts (search dropdowns, etc.)

### `<MfMonospaceId>`
Monospace span for TCGPlayer order numbers and TCGplayer Ids. No truncation.

**Props**:
- `value: string | number`

---

## Feedback

### `<MfConfirmDialog>` / `useConfirm()`
Wrapper around PrimeVue's `useConfirm` that enforces specific action verbs (`Delete`, `Reset`, `Clear` — never `OK`). Standard cancel + verb buttons.

**Usage**:
```ts
const confirm = useConfirm()
confirm({
  title: 'Clear all overrides?',
  body: 'This will reset N rows to their calculated price.',
  verb: 'Clear',
  destructive: true,
  onConfirm: () => { ... },
})
```

### `<MfToast>` / `useToast()`
Wrapper around PrimeVue's `useToast` with consistent positioning (top-right) and styling. Shorthand methods: `useToast().success(msg)`, `.error(msg)`, `.info(msg)`. Per [ux-patterns.md §Toasts](ux-patterns.md).

### `<MfErrorBanner>`
Inline error banner for above-table or above-form error states. Has a "Retry" or "Dismiss" action depending on context.

**Props**:
- `title?: string`
- `message: string`
- `onRetry?: () => void`

---

## File handling

### `<MfFileDropzone>`
File upload component for CSV / PDF imports. Drag-and-drop or click-to-browse. Validates file type by extension. Shows progress during upload.

**Props**:
- `accept: string` — e.g., `.csv` or `.csv,.pdf`
- `multiple?: boolean`
- `maxSize?: number` — bytes; default 200MB (PricingCustomExport is large)
- Emits `@upload(files)`, `@progress(pct)`, `@error(err)`

---

## Decisions

- **Color palette** — Mythic Orange `#EA5A1F`, Fox Teal `#2E899B`, Games Brown `#5C2D0E`. Defined in [ux-patterns.md §Brand colors](ux-patterns.md). Status pills stay semantic (Tailwind green/amber/red) to avoid muddling brand and meaning.
- **Iconography** — **PrimeIcons** throughout. Reference by class name (`pi pi-printer`, `pi pi-external-link`, etc.). Component wrappers that take an `icon` prop accept the name only (without the `pi pi-` prefix); the wrapper applies the prefix.
- **Component-level testing** — **deferred**. Pest covers backend; Vue component tests via Vitest + Vue Test Utils get added when something gets complex enough to warrant them, not from day one.

## Open questions

None. As per-page docs surface new components, this list grows organically.
