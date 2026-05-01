# UX Patterns

Cross-cutting UI conventions referenced by every per-page doc. Decisions made here aren't repeated downstream — page docs assume these defaults and only call out deviations.

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

| Token | Light hex | Dark hex | Use |
|---|---|---|---|
| `mf-orange` | `#EA5A1F` | `#FF7B45` | Primary CTAs, primary brand accents, key emphasis |
| `mf-teal` | `#2E899B` | `#5BB5C9` | Links, focus rings, secondary accents |
| `mf-brown` | `#5C2D0E` | `#D9B896` | Headings, deep accents (light); warm-toned emphasis (dark) |
| Neutrals | Tailwind `slate` / `gray` palette | Same — Aura inverts | Backgrounds, body text, borders, muted text |
| Semantic green / amber / red | Tailwind `emerald-500` / `amber-500` / `red-500` | Tailwind `emerald-400` / `amber-400` / `red-400` | Status pills, success / warning / error states. Kept semantic, not brand-tinted. |

Brand colors should never be used to encode meaning — orange ≠ "warning," teal ≠ "info." Use the semantic colors for state.

**Dark mode behavior**

The dark variants exist because the light values become unreadable on dark surfaces. Specifically:

- `mf-brown` (light: `#5C2D0E`) on a `slate-900` background fails contrast badly — it's a dark color on a dark background. The dark variant `#D9B896` is a warm cream that maintains the brand's warmth while staying readable.
- `mf-orange` and `mf-teal` are technically usable in light values on dark backgrounds, but their lighter dark-mode variants pop better — orange feels more vibrant, teal feels more like a link.
- All three dark variants stay within the original hue family so the brand identity carries across modes.
- Status-pill semantic colors shift one shade lighter in dark mode (e.g. `emerald-500` → `emerald-400`) for the same readability reason.

PrimeVue Aura handles the rest — surface backgrounds, body text, borders, and form-element chrome automatically invert when the `.dark` class is on `<html>` (already wired into the vue-starter-kit's appearance toggle in [resources/views/app.blade.php](../../resources/views/app.blade.php)).

**Implementation**

Tokens live as CSS custom properties so all consumers (Tailwind utilities, PrimeVue preset, raw CSS) read from one source:

```css
:root {
  --mf-orange: #EA5A1F;
  --mf-teal: #2E899B;
  --mf-brown: #5C2D0E;
}

html.dark {
  --mf-orange: #FF7B45;
  --mf-teal: #5BB5C9;
  --mf-brown: #D9B896;
}
```

The PrimeVue preset wires `--mf-orange` into Aura's primary semantic slot:

```ts
import Aura from '@primevue/themes/aura'
import { definePreset } from '@primevue/themes'

const MythicFoxPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50:  '{orange.50}',  100: '{orange.100}', 200: '{orange.200}',
      300: '{orange.300}', 400: '{orange.400}', 500: 'var(--mf-orange)',
      600: '{orange.600}', 700: '{orange.700}', 800: '{orange.800}',
      900: '{orange.900}', 950: '{orange.950}',
    },
  },
})
```

(The non-500 shades use Aura's built-in orange ramp; only `500` — the actual primary — is pinned to our brand value. Aura interpolates around it for hover/active/disabled states.)

Tailwind aliases map to the same CSS variables in `tailwind.config.ts`:

```ts
colors: {
  'mf-orange': 'var(--mf-orange)',
  'mf-teal':   'var(--mf-teal)',
  'mf-brown':  'var(--mf-brown)',
}
```

Then `bg-mf-orange`, `text-mf-teal`, `border-mf-brown` work everywhere and auto-swap with mode.

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
**Yes** — pagination, sort, and filters all serialize to query string. The shareability angle is irrelevant for a single-user app, but the other properties of URL state earn their keep:

- **Refresh-safe.** Accidentally hitting refresh doesn't lose filter context.
- **Back-button works correctly.** Navigate away, come back — filters restored.
- **Bookmarks are the saved-views mechanism.** Get a filter combo you use often? `Cmd+D` it. The browser is already a perfectly good saved-views manager — no need to build a custom one in the app.
- **Dashboard quick-action shortcuts.** Tiles like Import Orders pass `?import=1` to trigger destination-page modals on mount (per [dashboard.md](dashboard.md)). That mechanism only works because URL state is the convention.

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

**The whole app is mobile-first.** Every page must function on a 375px-wide screen — not just gracefully degrade. Touch targets ≥ 44 × 44px throughout. Some pages have layouts specifically tuned for phone use (Add Cards, public homepage); others use responsive patterns to keep heavy data tables usable on narrow screens.

**For data tables on narrow screens** (`< 768px`), `MfTable` switches its presentation:

- **Mobile**: each row renders as a stacked card via the `mobile-row` slot (see below). No horizontal scroll. Filters move into a full-screen drawer triggered by a filter button in the page header.
- **Tablet (768–1024px)**: standard table layout with horizontal scroll if columns overflow. Filter panel collapsible inline.
- **Desktop (≥ 1024px)**: standard table layout, filter panel above the table.

#### Mobile-row slot pattern

`MfTable` exposes a single `mobile-row` slot that pages fill in once. Below 768px the table hides its headers, omits column borders, and renders each row by passing the row data into this slot. The page is responsible only for what one card looks like — pagination, sorting, filtering, selection, expand state, and skeleton loading are still owned by `MfTable`.

```vue
<MfTable :endpoint :columns :selectable>
  <template #mobile-row="{ row, selected, toggleSelect, expanded, toggleExpand }">
    <div class="mf-card-row" :class="{ selected }">
      <input v-if="selectable" type="checkbox" :checked="selected" @change="toggleSelect" />
      <MfCardIdentity :card="row" />
      <div class="qty">Qty {{ row.quantity }}</div>
      <!-- page-specific content here -->
    </div>
  </template>
</MfTable>
```

**Slot props** (all stable across pages):

| Prop | Type | Notes |
|---|---|---|
| `row` | object | Full row data, same shape as desktop cells receive |
| `selected` | boolean | True if row is in the current selection (only meaningful when `selectable` is on) |
| `toggleSelect` | () => void | Toggles selection for this row |
| `expanded` | boolean | True if this row is currently expanded (only meaningful when `expandable` is on) |
| `toggleExpand` | () => void | Toggles expand state |

**Why a single slot, not a templating contract**: declarative metadata (column definitions auto-mapped to mobile sections) was tempting but doesn't survive contact with real pages. Inventory needs inline-edit cells, Catalog needs tap-to-expand with sub-rows, Orders needs paired action icons — these are all distinct shapes that compress poorly into a metadata schema. A flexible slot is a better trade than a rigid one.

**Pages with simple tables** — small column count, no inline editing — can omit the `mobile-row` slot, in which case `MfTable` falls back to a horizontal-scroll table layout on mobile. Called out per page.

The exact shape of each page's mobile card is illustrated in that page's "Mobile layout" subsection (e.g. [catalog.md](catalog.md), [inventory.md](inventory.md), [orders-table.md](orders-table.md), [settings.md](settings.md)). Those examples are visual references for what the slot template renders — the mechanism for getting them on screen is uniform.

**Forms and modals**: full-width inputs on mobile, max-width centered on desktop. Modals become full-screen sheets on phones, centered dialogs on tablet/desktop.

---

## Forms

### Validation
- **Server-side validation** is the source of truth (Laravel form requests + Inertia's `errors` prop)
- **Client-side validation** is enhancement only — never block submit on client failure if server would accept
- Errors render inline beneath the field (red text, no icons)
- Submit button disabled while a submit is in flight; spinner inside the button label

### Input types
- **Money** — PrimeVue InputNumber with `mode="currency" currency="USD" locale="en-US"`. Two-decimal format. Stored in cents; the InputNumber binds to a v-model that converts cents ↔ dollars at the boundary.
- **Quantity** — PrimeVue InputNumber, integer, min=0, with +/- buttons sized for touch (≥ 44px tap targets) since these inputs appear on phones across multiple pages.
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

Sticky across all admin pages. Sections: **Dashboard · Orders · Catalog · Inventory · Settings**. Right side: user menu (avatar/name → "Log out"). Mythic Fox logo on the left links to Dashboard.

**Mobile behavior** (`< 768px`): the horizontal nav collapses into a hamburger menu on the left. Tapping opens a full-screen drawer with the section list and the user menu. The Mythic Fox logo stays visible center/top. This avoids cramming five nav items into a phone-width strip.

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

## Things to consider

- **Status pill colors aren't accessible by color alone.** Color-blind users can't distinguish green/amber/red without help. Add a small icon (✓ / ⏱ / ✕) inside the pill, or a text label, so the meaning carries even without color perception.
- **Dark mode coverage is at the token layer, not the layout layer.** Brand-color CSS variables swap on `.dark` and PrimeVue Aura inverts surfaces automatically — but per-page mockups and ASCII layouts in this doc set were drafted assuming light-mode shading. Spot-check each page in dark mode during build; small contrast or border tweaks may surface.
- **PrimeVue version churn.** PrimeVue major versions occasionally introduce breaking changes (component prop renames, API shifts). Pin a major version and update deliberately rather than auto-tracking; the `Mf*` wrappers help insulate page code but they themselves need to be updated.
- **Wayfinder typed routes** require regenerating after route changes. Add the regeneration to the post-deploy / dev-server scripts so it doesn't go stale silently.
