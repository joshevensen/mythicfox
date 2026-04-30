# Catalog

Browse every card the system knows about — the full TCGPlayer catalog seeded from `PricingCustomExport.csv` uploads. Includes cards with zero stock. Read-mostly.

**Route**: `/catalog`
**Access**: admin
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [catalog-schema.md](../catalog-schema.md)

---

## Purpose

Lookup and browse view for the seller. Primary use cases:

- Look up a specific card by name or number
- Browse what's in a set
- Spot-check that a fresh `PricingCustomExport` import landed correctly
- See which cards are in stock and total quantity per card across all condition variants
- Get TCGplayer Id / SKU for a specific (card, condition) variant

Pricing decisions and override management happen on the Inventory page, not here.

---

## Layout

### Page header

| Element | Behavior |
|---|---|
| Title | "Catalog" |
| Stale-data indicator | Small inline text next to the upload button: e.g. "Magic refreshed 2 days ago", "Lorcana refreshed 8 days ago". Surfaces `products.priced_at` per product. Highlighted when any value is older than 3 days. |
| **Upload PricingCustomExport** (primary button) | Opens an `MfFileDropzone` modal. See §Upload flow. |

No "File history" button — the Files page handles that.

### Filter panel

Collapsible (`MfFilterPanel`), sits above the table.

| Filter | Type | Default |
|---|---|---|
| Product | single-select dropdown | "All products" |
| Set | multi-select, chained to Product | "All sets" |
| In stock | toggle | off — i.e. show all cards |

`In stock = on` shows only cards where **any** condition variant has `inventory.quantity > 0`. (Catalog cards with no inventory rows or all-zero inventory are hidden.)

Active filters render as removable chips above the table per the standard pattern. Filter state lives in the URL.

### Table

PrimeVue DataTable in `lazy` mode with **expand rows** (PrimeVue's `expandable-row-groups` is not what we want — we use the per-row expand toggle).

#### Parent row columns

One row per **(set, product_name, number)** — a card. Aggregates across condition variants.

| Column | Source | Sortable | Notes |
|---|---|---|---|
| `▸` (expand toggle) | — | no | PrimeVue expand chevron |
| Card Name | `cards.product_name` | yes | Default sort, ascending |
| Number | `cards.number` | yes | Collector number; visually compact |
| Set Name | `sets.name` | yes | |
| Rarity | `cards.rarity` | yes | Verbatim TCGPlayer value |
| Total Qty | `SUM(inventory.quantity)` across condition variants | yes | Renders as `0` if no inventory rows exist; right-aligned |

**Default sort**: Card Name ascending.

The aggregation key is `(set_id, product_name, number)`. Within that group, every condition variant is a child row (see expand row).

#### Expand row

When the user clicks the expand toggle, the row opens to reveal one sub-row per condition variant of that card. Rendered as a nested table inside the parent row's expanded area.

| Sub-column | Source | Notes |
|---|---|---|
| Condition | `cards.condition` | Full TCGPlayer condition string verbatim, e.g. `Near Mint`, `Near Mint Unlimited Edition Rainbow Foil` |
| Quantity | `inventory.quantity` | `0` if no inventory row exists for this `card_id` |
| TCGplayer ID | `cards.tcgplayer_id` | Monospace (`MfMonospaceId`); the TCGPlayer-assigned identifier for this exact (product, condition) SKU |

Sub-rows are sorted by Condition (alphabetical) — minor decision that can be tuned later.

No prices shown on this page. Prices live on the Inventory page.

---

## Interactions

### Filtering

Standard `MfFilterPanel` behavior. Two notable details:

- **Set filter is chained**: when Product changes, the Set filter's options update to that product's sets. Existing Set selections that don't match the new Product are dropped silently with a small toast: *"Removed 3 set filters not in Magic"*.
- **In stock** triggers a `HAVING SUM(inventory.quantity) > 0` style filter on the aggregated query.

### Sorting

Click a column header to toggle asc → desc → unsorted (single-column sort, server-side). Total Qty sorts on the aggregate, not on any individual variant's quantity.

### Row click

Click anywhere on the parent row (or the explicit `▸` toggle) → expands inline. Click again → collapses. No navigation, no modal — pure inline expand.

### Upload flow

1. Click **Upload PricingCustomExport**. Modal opens with `MfFileDropzone` accepting `.csv`, max size 200MB.
2. User drops or selects a file. Client-side validation: extension only.
3. On submit: upload to server, queue a processing job, dismiss modal, show toast: *"PricingCustomExport queued — refreshing catalog…"*
4. Job progress is reflected by polling or Inertia partial reloads. While processing, the upload button shows a small spinner badge ("Importing…").
5. On completion, replace the spinner with a success indicator briefly, then return to normal. Final toast: *"Refreshed N cards across {Product Name}"*.
6. The catalog table auto-reloads at completion.

If the file fails validation server-side (wrong header, missing columns), an `MfErrorBanner` appears at the top of the page with the parse error. The file is still saved to `files` for inspection.

---

## Data

Reads:

- `cards` (joined to `sets`, joined to `products`)
- `inventory` (left-join, aggregated)
- `products.priced_at` (for the stale-data indicator)

Writes:

- Catalog imports go through the standard PricingCustomExport flow per [catalog-schema.md](../catalog-schema.md). The page itself does no direct writes other than triggering the upload.

The aggregated parent-row query is non-trivial. A view or materialized query class is appropriate; index hints below.

### Indexes (DB)

- `cards (set_id, product_name, number)` — supports the GROUP BY for parent rows
- `cards (tcgplayer_id)` — already unique
- `cards (product_name)` — supports default sort
- `inventory (card_id)` — already unique
- Foreign-key indexes on `cards.set_id`, `sets.product_id`

---

## States

| State | Display |
|---|---|
| Empty (no catalog rows ever) | `MfEmptyState`: "Your catalog is empty." Body: "Upload a TCGPlayer PricingCustomExport to seed it." CTA: opens the same upload modal. No filter panel rendered. |
| Empty (filters return zero rows) | "No cards match these filters." + Clear filters button. |
| Loading | Skeleton rows (PrimeVue built-in) per the standard pattern. |
| Error | `MfErrorBanner` above the table with retry; previously-loaded rows stay visible. |
| During import | Upload button shows "Importing…" spinner; existing table rows unchanged until refresh. |
| Stale data | "Magic refreshed 8 days ago" rendered in amber/warning text next to the upload button when any product's `priced_at` is null or older than 3 days. |

---

## Open questions

None.
