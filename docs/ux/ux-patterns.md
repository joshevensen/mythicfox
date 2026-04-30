# UX Patterns

Cross-cutting UI conventions referenced by every per-page doc. Decisions made here aren't repeated downstream — page docs assume these defaults and only call out deviations.

This file replaces gap #3 from the old gaps.md.

---

## Stack

- **PrimeVue** for all primitive UI components (DataTable, InputNumber, Dialog, Menu, MultiSelect, etc.)
- **PrimeIcons** for all iconography. Reference icons by name (e.g. `pi pi-printer`, `pi pi-external-link`, `pi pi-download`). No mixing with other icon libraries.
- **Inertia + Vue 3 + TypeScript** for page rendering
- **Tailwind** for layout/spacing only — never re-style PrimeVue components; use their theming hooks
- **Wayfinder** for typed route helpers (no hardcoded URL strings)

---

## Brand colors

Extracted from the Mythic Fox Games logo. The PrimeVue theme is configured to use these as primary/secondary; Tailwind utility classes get matching aliases.

| Token | Hex | Use |
|---|---|---|
| `mf-orange` | `#EA5A1F` | Primary CTAs, primary brand accents, key emphasis |
| `mf-teal` | `#2E899B` | Links, focus rings, secondary accents |
| `mf-brown` | `#5C2D0E` | Headings on light backgrounds, deep accents |
| Neutrals | Tailwind `slate` / `gray` palette | Backgrounds, body text, borders, muted text |
| Semantic green / amber / red | Tailwind `emerald-500` / `amber-500` / `red-500` (default) | Status pills, success / warning / error states. Kept semantic, not brand-tinted. |

Brand colors should never be used to encode meaning — orange ≠ "warning," teal ≠ "info." Use the semantic colors for state.

---

## Lists / Tables

The orders table, the catalog (inventory), and the file-history list all share one pattern.

### Component
PrimeVue **DataTable** in `lazy` mode — server-side pagination, sorting, and filtering. The component never receives a full result set; it requests one page at a time.

### Pagination
- **Default page size**: 50
- **Page size options**: 25, 50, 100, 200
- **Pagination control**: bottom-aligned, shows "Showing N–M of T", page numbers, prev/next, jump-to-page
- **Server endpoint**: standard Laravel paginator (`->paginate()`), JSON response shape matches what PrimeVue's `lazy` mode expects (rows + `totalRecords`)

### Sorting
- Single-column sort, server-side
- Click column header to toggle: unsorted → asc → desc → unsorted
- Sort indicator visible in the header
- Default sort per page (specified per-page doc)

### Filtering
- Filters live in a **collapsible panel above the table**, not in the column headers (column-header filters get cramped on narrow screens)
- Filter types per data type:
  - **Text** (search): single text input, debounced 300ms, applies to a defined set of columns per page (specified per-page)
  - **Enum** (e.g., product, condition, status string): PrimeVue MultiSelect — multi-select chips
  - **Boolean** (e.g., "has override"): toggle switch
  - **Range** (e.g., qty, price): two number inputs (min / max)
  - **Date range**: PrimeVue Calendar with range mode
- Active filters render as removable chips above the table; clicking the X on a chip removes that filter
- "Clear all filters" button appears when ≥1 filter is active

### URL-driven state
**Yes** — pagination, sort, and filters all serialize to query string. URLs are shareable / bookmarkable / refresh-safe.

Query string format:
```
?page=2&per_page=50&sort=order_date&dir=desc&product=Magic,Lorcana&search=boltyn
```

- Multi-value filters comma-separated.
- `dir` is `asc` or `desc`.
- Inertia's `router.get(url, { preserveState: true })` updates the URL on every interaction.

### Selection
For tables that need bulk actions:
- Checkbox column on the leftmost edge, including a master checkbox in the header
- Master checkbox selects only the current page (not all matching rows across pages); a "Select all N matching" link appears when the page-level master is checked
- Selected count + bulk action menu render in a sticky action bar at the **top** of the table

### Empty / loading / error states
- **Empty (no data exists)**: centered message + an actionable CTA where applicable (e.g., "No orders yet — import your first order CSVs")
- **Empty (filters return zero rows)**: "No matches" message + "Clear filters" button
- **Loading**: PrimeVue's built-in skeleton rows during `lazy` fetches; full-table spinner only on initial load
- **Error**: inline error banner above the table with a "Retry" button; the previously-loaded rows stay visible underneath if they exist

### Row interactions
- Click anywhere on a row → navigate to detail page or open detail modal (specified per-page)
- Hover state: subtle background change

### Responsive behavior
The app is admin-only and assumed used on desktop or tablet. The Add Cards page is the only flow optimized for mobile (sort cards in hand, scroll & increment). Other pages may render with horizontal scroll on narrow screens — that's acceptable.

---

## Forms

### Validation
- **Server-side validation** is the source of truth (Laravel form requests + Inertia's `errors` prop)
- **Client-side validation** is enhancement only — never block submit on client failure if server would accept
- Errors render inline beneath the field (red text, no icons)
- Submit button disabled while a submit is in flight; spinner inside the button label

### Input types
- **Money** — PrimeVue InputNumber with `mode="currency" currency="USD" locale="en-US"`. Two-decimal format. Stored in cents; the InputNumber binds to a v-model that converts cents ↔ dollars at the boundary.
- **Quantity** — PrimeVue InputNumber, integer, min=0, with +/- buttons. Tappable on mobile (Add Cards page).
- **Text search** — debounced 300ms before triggering server fetch.
- **Date** — PrimeVue Calendar, ISO display format `YYYY-MM-DD`.

### Confirmation
Destructive actions confirm via PrimeVue's `useConfirm` dialog. One-line title + body, "Cancel" + "{Verb}" buttons. Verbs are specific (`Delete`, `Reset`, `Clear`, etc. — not `OK`).

---

## Display

### Money
Per [saas-design.md §Monetary values](../saas-design.md):
- Format: `$0.20`, `$10.11`, `$1,234.56` — always two decimals, comma thousands, `$` prefix.
- Right-aligned in numeric table cells.
- Use the `useMoney(cents)` composable, not inline formatting.
- Null money values render as `—` (em-dash), not `$0.00`.

### Dates
- Display: `MMM D, YYYY` — e.g., `Nov 14, 2025`. Same in tables, modals, and PDFs.
- Datetimes: `MMM D, YYYY h:mma` — e.g., `Nov 14, 2025 1:41pm`. Used for `imported_at`, `created_at`, file timestamps.
- Storage and API are always ISO; formatting is presentation-layer only.
- Use the `useDate(value, format?)` composable.

### Status / state
TCGPlayer's status string (`Completed - Paid`, `Canceled`) renders as a colored pill:

| Value | Color | Background |
|---|---|---|
| `Completed - Paid`, tracking populated | white text | green |
| `Completed - Paid`, tracking null | white text | amber |
| `Canceled` | white text | red |
| any other / unknown | dark text | neutral gray |

The same logic appears on the orders table (status column), the order detail page (header), and any "open orders" widget. Centralized in a single `StatusPill` component.

### Identifiers
- **TCGPlayer order numbers** (`623394E9-23CAFE-565FC`): rendered in monospace, full string. Long but distinctive — no truncation.
- **TCGplayer Id**: monospace, integer.
- **Card collector numbers** (`BOL022`, `97/204`): rendered as part of the card identity block (Product · Set · Name · #Number), not standalone.

### Card identity
Wherever a card is named in the UI (catalog row, order line item, picker, search result), use this format:

```
{Product Name}  ·  #{Number}  ·  {Set Name}
{Condition}                        {Rarity}
```

Two-line stack: product name + number + set on top, condition + rarity beneath in muted text. This keeps catalog rows scannable when many cards share names.

---

## Navigation

### Top nav
- Sticky horizontal nav bar across all admin pages
- Sections: **Dashboard · Orders · Catalog · Inventory · Settings**
- Right side: user menu (avatar/name → "Log out")
- Mythic Fox logo on the left links to Dashboard

**Catalog vs Inventory** are distinct: Catalog browses every card the system knows about (seeded from PricingCustomExport), including ones with zero stock. Inventory shows only cards currently stocked, with override-pricing controls and the "Export Pricing" round-trip. Pricing actions live inside whichever page they apply to — there is no standalone Pricing nav item.

**File history** is a section of Settings, not its own nav item. Imports and exports are administrative concerns; collapsing them under Settings keeps the top nav focused on workflow surfaces.

### Page headers
Every admin page has a header band beneath the top nav:
- Page title (h1) on the left
- Primary action button(s) on the right (e.g., "Export Pricing", "Add Cards")
- Breadcrumbs only on detail pages (e.g., Orders → Order #623394E9...)

### Toasts
PrimeVue Toast for transient feedback — successful saves, completed exports, file uploads done. Top-right, auto-dismiss after 4 seconds. Errors stay until dismissed.

---

## Permissions

There is one user (the owner). Every admin page requires authentication via Fortify. The public homepage is the only unauthenticated route. No role/permission system; no per-page authorization beyond "logged in vs not."

---

## Open questions

None right now. Everything above is a proposal — push back on any point and we'll adjust before drafting the per-page docs that depend on it.
