# Orders Table

List view of every imported order. The starting point for printing packing slips, looking up an order, or batch-importing a new pile of TCGPlayer exports.

**Route**: `/orders`
**Access**: admin
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [order-schema.md](../order-schema.md)

---

## Purpose

Day-to-day operations view for orders. Primary uses:

- **Import a fresh batch** of TCGPlayer exports (OrderList + ShippingExport + PullSheet + PackingSlips PDF).
- **Print packing slips** — single order from a row action, or bulk for selected rows.
- **Jump to TCGPlayer** for a specific order when more detail or actual editing is needed.
- **See what's outstanding** at a glance via the status column.

There is **no in-app order detail page**. Orders are read-only historical snapshots in mythicfox; everything beyond what this table shows is one click away in the TCGPlayer Seller Portal.

---

## Layout

### Page header

| Element | Behavior |
|---|---|
| Title | "Orders" |
| **Import orders** (primary button) | Opens a modal multi-file dropzone for the four-file batch upload. See §Import flow. |

### Filter panel

| Filter | Type | Default |
|---|---|---|
| Status | multi-select (raw `tcgplayer_status` strings) | "All" |
| Date range | `MfDatePicker` range | Last 90 days |

The Status options are populated from `DISTINCT tcgplayer_status` in the DB so any new TCGPlayer status string appears automatically without code changes. Observed values today: `Completed - Paid`, `Canceled`.

The 90-day default mirrors TCGPlayer's own export window — most operational work stays inside that range. Users can extend the range when needed.

### Table

PrimeVue DataTable in `lazy` mode. **Selectable rows** for bulk packing-slip printing.

| Column | Source | Sortable | Notes |
|---|---|---|---|
| ☐ | (selection) | — | |
| Order # | `tcgplayer_order_number` | yes | Monospace (`MfMonospaceId`) |
| Date | `order_date` | yes | Default sort, desc; formatted `MMM D, YYYY` |
| Buyer | `buyer_name` | yes | Plain text — no link |
| Items | `item_count` | yes | Right-aligned integer |
| Total | `total_amount` | yes | `MfMoney`; right-aligned |
| Status | derived from `tcgplayer_status` + `tracking_number` | yes (sorts by `tcgplayer_status` alphabetically) | `MfStatusPill` per [ux-patterns.md §Status](ux-patterns.md) |
| (actions) | — | — | Right-edge icon column |

Sorting "Status" is a sort by the underlying `tcgplayer_status` string — the pill colors are derived from status + tracking but the column doesn't sort on the pill state itself. Acceptable for now; if users want pill-state sorting later it's a small server-side query change.

The row itself is **not clickable**. All actions are explicit icons in the actions column.

---

## Interactions

### Per-row actions

Two icon buttons at the right edge of each row:

| Icon | Tooltip | Behavior |
|---|---|---|
| 🖨️ printer | "Print packing slip" | Opens a new tab to `/orders/{tcgplayer_order_number}/packing-slip`. The route renders the slip per [packingslip-spec.md](../packingslip-spec.md) and auto-triggers the browser print dialog (`window.print()` on load). |
| 🔗 external | "Open in TCGPlayer" | Opens a new tab to `https://sellerportal.tcgplayer.com/orders/{tcgplayer_order_number}` (`target="_blank" rel="noopener"`). For full order detail, status changes, refunds, messages, etc. — handled in TCGPlayer, not mythicfox. |

The app does not track whether a slip has been printed — printing is a presentation concern, not state.

### Bulk action: Print packing slips

Standard top-of-table action bar (per [ux-patterns.md §Selection](ux-patterns.md)) appears when ≥1 row is selected. Master checkbox + "Select all N matching" link.

- **Action**: "Print packing slips"
- **Behavior**: opens a new tab to `/orders/print?ids={comma-separated order numbers}`. Renders all selected orders' slips in one document — every order produces a two-page pair (address side + slip side per [packingslip-spec.md](../packingslip-spec.md)) with `page-break-after: always` between sides. Browser print dialog auto-triggers.
- The user prints once, gets the whole batch in one duplex pass.

This is the only bulk action.

### Filtering, sorting, pagination

Standard `MfTable` behavior per [ux-patterns.md](ux-patterns.md). URL state persists pagination, sort, filters.

---

## Import flow

Triggered by the **Import orders** primary button. PrimeVue Dialog modal.

### Modal layout

Four labeled file slots — one per source file in [order-schema.md](../order-schema.md):

| Slot | Required | Accepts |
|---|---|---|
| OrderList | required | `.csv` (validated by header) |
| ShippingExport | optional | `.csv` |
| PullSheet | optional | `.csv` |
| PackingSlips | optional | `.pdf` |

Each slot is its own `MfFileDropzone` that accepts a single file. Drop or browse. The slot label updates to show the filename once selected. A small × clears the slot.

OrderList is the only required file — it's the source of truth for "what orders exist." Without ShippingExport, addresses/tracking are null. Without PullSheet, no line items. Without PackingSlips PDF, line item prices are null. These trade-offs are documented in [order-schema.md](../order-schema.md); the modal surfaces them as small hints under the optional slots.

### Submit

"Import" button (disabled until at least OrderList is provided).

On submit:

1. Upload all provided files.
2. Persist each via the `files` table per [saas-design.md §Path convention](../saas-design.md).
3. Queue an import job; close the modal; show toast: *"Import queued — processing N orders…"*.
4. While the job runs, the import button shows "Importing…" with a spinner badge.
5. On completion: success toast: *"Imported N orders (M new, K updated)."* Table auto-reloads.

If any file fails validation server-side, an `MfErrorBanner` appears at the top of the page with details. Files that did parse are still processed; failed files are saved to `files` for inspection but don't contribute data.

---

## Data

Reads:

- `orders` (with computed effective status from `tcgplayer_status` + `tracking_number`)

Writes:

- Import flow → `orders`, `order_items`, `files` per [order-schema.md §Import flow](../order-schema.md). The page itself does no direct writes.

### Indexes (DB)

- `orders (order_date)` — supports default sort and date-range filter
- `orders (buyer_name)` — supports buyer search if added later
- `orders (tcgplayer_order_number)` — already unique (upsert key)
- `orders (tcgplayer_status)` — supports status filter

---

## Mobile layout

On screens `< 768px`, each order renders as a stacked card instead of a table row:

```
┌──────────────────────────────────────┐
│  Joseph Current                      │  ← buyer, primary
│  623394E9-8874A5-0BD46               │  ← order #, monospace, muted
│                                      │
│  Apr 14, 2026  ·  3 items  ·  $12.73 │
│                                      │
│  [Completed - Paid · shipped pill]   │
│                                      │
│  [🖨 Print slip]   [🔗 TCGPlayer]     │
└──────────────────────────────────────┘
```

Filter panel becomes a full-screen drawer triggered by a filter button in the page header. Bulk-selection checkbox sits at the top-left of each card. The Import Orders header button stays primary; on phones it sticks to the bottom of the viewport so it's reachable with one thumb.

---

## States

| State | Display |
|---|---|
| Empty (no orders ever) | `MfEmptyState`: "No orders yet." Body: "Import your first batch of TCGPlayer order exports." CTA: opens the import modal. |
| Empty (filters return zero rows) | "No orders match these filters." + Clear filters button. |
| Loading | Skeleton rows. |
| Error | `MfErrorBanner` above the table; previously-loaded rows stay visible. |
| Import in flight | Import button shows "Importing…" with spinner. Existing table rows unchanged until refresh. |
| Import success | Toast and auto-reload. |
| Import partial failure | Some files parsed, some didn't. Banner shows which failed and why. Successful files still applied. |
