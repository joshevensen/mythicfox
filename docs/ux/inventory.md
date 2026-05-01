# Inventory

Manage stocked cards: edit quantities, set per-card price overrides, and run the **Export Pricing** round-trip back to TCGPlayer. Every row is one (card, condition) SKU — i.e. one row per `inventory` record.

**Route**: `/inventory`
**Access**: admin
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [catalog-schema.md](../catalog-schema.md)

---

## Purpose

The seller's day-to-day stock and pricing dashboard. Where you:

- Adjust quantities (after a manual count, off-platform sale, or correction)
- Set or clear price overrides when the algorithm's number disagrees with your gut
- Push current prices back to TCGPlayer via the pricing CSV
- Spot which products have stale market data

Browsing the wider catalog (cards you don't stock) lives on the [Catalog page](catalog.md). This page is intentionally narrow: it only shows rows that exist in `inventory`.

---

## Layout

### Page header

| Element | Behavior |
|---|---|
| Title | "Inventory" |
| Stale-data indicator | Inline next to the Export button, e.g. "Magic prices are 5 days old". Surfaces `products.priced_at`; amber when any product is >3 days stale. Same indicator pattern as [Catalog](catalog.md). |
| Override count | Small clickable indicator: "12 overrides active". Click toggles the **Has override** filter. |
| **Export Pricing** (primary button) | Triggers the recompute → preview modal flow. See §Export Pricing flow. |

### Filter panel

Three filters are **required** — the table renders no rows until all three are chosen. This is intentional: the page is designed for narrow, focused work (one product / one set / one condition at a time), not whole-inventory browsing.

| Filter | Type | Required? | Default |
|---|---|---|---|
| Product | single-select dropdown | yes | none |
| Set | multi-select, chained to Product | yes (≥1) | none |
| Condition | multi-select | yes (≥1) | none |
| Has override | toggle | no | off |
| In stock | toggle | no | **off** (zero-qty rows shown by default; see §Why show zero-qty) |

The empty filter state shows: *"Pick a product, set, and condition to view inventory."* — no skeleton rows, no table headers, just the prompt above an outlined placeholder zone. As soon as all three required filters are chosen the table renders.

URL state still applies — bookmarks and back-button restore the full filter set, so revisiting the same view is one URL away.

### Table

PrimeVue DataTable in `lazy` mode. **Selectable rows** for bulk actions. One row per `inventory` record (flat — no expand rows).

| Column | Source | Sortable | Editable |
|---|---|---|---|
| ☐ | (selection) | — | — |
| Card Name | `cards.product_name` | yes | no |
| Number | `cards.number` | yes | no |
| Market | `cards.market_price` | yes | no |
| Low | `cards.low_price` | yes | no |
| Calculated | `inventory.calculated_price` | yes | no |
| **Override** | `inventory.override_price` | yes | **yes (inline)** |
| **Qty** | `inventory.quantity` | yes | **yes (inline)** |
| (actions) | — | — | — |

Set, Product, Condition, Rarity, and the Effective price are intentionally **not** columns — Set/Product/Condition are filter-fixed, Rarity isn't critical here, and Effective is derivable (it's `Override` if set, else `Calculated`). The CSV export uses Effective, not anything visible in this table.

**Default sort**: Card Name ascending.

#### Why show zero-qty by default

Zero-qty rows still hold meaningful state — an `override_price` you set deliberately when you sold out, the last `calculated_price` from a prior export, etc. Hiding them by default would silently hide overrides. The "In stock" toggle exists for the moments you want to focus on what's currently for sale.

---

## Interactions

### Inline edit — Quantity

Click the Qty cell → renders an `MfQtyInput` in place. Enter or blur saves; Escape cancels. Inertia partial reload persists. Toast on save: *"Updated"* (subtle; doesn't auto-dismiss faster than the standard 4s).

### Inline edit — Override

Click the Override cell → renders an `MfMoneyInput` in place, pre-filled with the current override value (or empty if null).

| Input state | Saves as |
|---|---|
| Number entered | `override_price = X cents` |
| Empty / cleared | `override_price = null` (effective price reverts to `calculated_price`) |

Saves on Enter or blur; cancel on Escape. The "12 overrides active" indicator at the top updates live.

### Save semantics (inline edits)

Both Qty and Override inline edits follow the same save pattern. Designed to handle rapid editing (e.g. tabbing through cells) without queue thrashing or lost writes.

| Rule | Behavior |
|---|---|
| **Skip no-ops** | If the cell value didn't change, no save fires. Tab-through-without-editing produces zero network traffic. |
| **Coalesce in-flight saves to the same cell** | If a save is already in flight for the cell being edited, abort the in-flight request (`AbortController`) and dispatch a new one with the latest value. |
| **Last-write-wins** | If two saves to the same cell race despite coalescing (e.g. abort didn't reach the server in time), the second response is authoritative. The cell renders whatever value the server confirms. |
| **Independent cells run in parallel** | Editing different cells never blocks each other. Each cell tracks its own save state. |

No blunt debouncing on blur — a 200–300ms delay would feel laggy when editing quickly. Coalescing is invisible to the user and accomplishes the same goal.

Server-side: each save is an idempotent UPDATE on a single inventory row. Concurrent updates to different rows don't conflict. Concurrent updates to the same row collapse to last-write-wins via the standard transaction.

### Per-row actions

Right-edge actions column. Two icon buttons (or a kebab menu if cramped):

| Action | Icon | Visible when | Behavior |
|---|---|---|---|
| Reset to calculated | undo / refresh | `override_price IS NOT NULL` | Sets `override_price = null` after `MfConfirmDialog` ("Reset to calculated price?"). Effective price reverts to `calculated_price`. |
| Remove from inventory | trash | always | Sets `quantity = 0` and `override_price = null` after confirm ("Remove from inventory?"). The `inventory` row is **soft** — never hard-deleted. The `cards` row stays in catalog. |

Hard delete of inventory rows is not exposed — keeping the row preserves history and lets a re-acquisition pick up where you left off.

### Bulk actions

Standard top-of-table action bar (per [ux-patterns.md §Selection](ux-patterns.md)) appears when ≥1 row is selected. Master checkbox + "Select all N matching" link.

| Action | Behavior |
|---|---|
| Clear overrides | `MfConfirmDialog` shows: "Clear overrides on N rows?" Sets `override_price = null` for selected. Effective price reverts to `calculated_price`. |
| Mark out of stock | `MfConfirmDialog` shows: "Set quantity to 0 on N rows?" Sets `quantity = 0` for selected. `override_price` is preserved (not cleared) — different from the per-row Remove action. |

---

## Export Pricing flow

Triggered by the primary header button. Modal-based — all interaction stays on this page.

### Step 1 — Recompute

Click **Export Pricing** → server runs the dual-input pricing algorithm against **every** `inventory` row (not just the filtered view). Persists results to `inventory.calculated_price`. Never touches `override_price`. Per [catalog-schema.md §Pricing export](../catalog-schema.md).

### Step 2 — Preview modal

PrimeVue Dialog opens at ~80% width. Title: **"Pricing changes"**.

**Subtitle**: *"N rows have changed effective prices since your last export."* (Or *"No price changes since your last export."* if none.)

**Toggle** at the top of the modal: ☐ Show all rows (default off → only changed rows).

**Table inside the modal**:

| Column | Source |
|---|---|
| Card | `MfCardIdentity compact` (single-line: Name · #Number · Set · Condition) |
| Old | `inventory.last_exported_price` (formatted `MfMoney`); `—` if never exported |
| New | current effective: `COALESCE(override_price, calculated_price)` |
| Δ | computed delta in cents, colored green (up) / red (down) / neutral (`—`) |

"Changed" means `last_exported_price ≠ current effective`. With no last-exported value (first export ever), every row counts as changed.

Footer buttons:

- **Cancel** — closes the modal. Recompute already happened, so `calculated_price` values stay updated; `last_exported_price` is unchanged.
- **Download CSV** — generates the MyPricing-format CSV per [catalog-schema.md §Output column map](../catalog-schema.md), persists via `files`, triggers download. After success: updates `inventory.last_exported_price = current effective` for every row, closes the modal, shows toast: *"Pricing CSV downloaded — N rows, M changed."*

### Schema addition

This flow needs a new column on `inventory`:

|Column|Type|Notes|
|---|---|---|
|`last_exported_price`|integer nullable|cents — the effective price at the moment of the last successful pricing export. Used as the comparison baseline for the preview modal. Set by Step 2 above; never edited manually.|

I'll add this to [catalog-schema.md](../catalog-schema.md) when we commit.

---

## Data

Reads:

- `cards` (joined to `sets`, joined to `products`)
- `inventory`
- `products.priced_at` (stale-data indicator)

Writes:

- `inventory.quantity` (inline + bulk + per-row Remove)
- `inventory.override_price` (inline + bulk + per-row Reset)
- `inventory.calculated_price` (Export Pricing recompute)
- `inventory.last_exported_price` (Export Pricing download)
- `files` (Export Pricing emits a row)

---

## Mobile layout

Standard responsive behavior per [ux-patterns.md §Responsive behavior](ux-patterns.md). Page-specific deviations:

- **Card-row layout** on screens `< 768px` (the column count is too wide for a phone):
  ```
  ┌──────────────────────────────────────┐
  │ ☐  Boltyn                            │
  │    #BOL001                           │
  │                                      │
  │    Market $0.19    Low $0.13         │
  │    Calculated $0.04                  │
  │    Override [    edit    ]           │
  │    Qty      [    3       ]           │
  │                                      │
  │    [↺ Reset]   [🗑 Remove]            │
  └──────────────────────────────────────┘
  ```
- **Override and Qty cells expose their inline edit on tap**, same behavior as desktop.
- **Bulk action bar** (visible when ≥1 row selected) stays sticky at the top of the card list.
- **Export Pricing primary button sticks to the bottom of the viewport** on phones for one-thumb reach.
- **Preview modal's Old / New / Δ table** scrolls horizontally inside the full-screen sheet if columns overflow (each row is short, so this is rare).
- **Required-filter empty state is unchanged**: "Pick a product, set, and condition to view inventory."

---

## States

| State | Display |
|---|---|
| Filters not all set | "Pick a product, set, and condition to view inventory." Big empty placeholder; no table headers. |
| Filters set, zero results | "No inventory matches these filters." + Clear filters button. |
| Loading | Skeleton rows. |
| Error | `MfErrorBanner` above the table; previously-loaded rows stay visible. |
| Inline-edit save in flight | Cell border subtly highlighted; spinner inside the cell. |
| Inline-edit save error | Cell border red, error tooltip on hover; value reverts to pre-edit. |
| Stale data | Amber "Magic prices are 5 days old" beside the Export button. |
| Recompute in flight | Export button shows spinner; preview modal opens once recompute completes. |
| Pricing export download success | Toast: "Pricing CSV downloaded — N rows, M changed." |
| Pricing export download failure | Modal stays open, error banner inside the modal. `last_exported_price` is **not** updated. User can retry. |

---

## Things to consider

- **Required-filter empty state vs deep-link.** A bookmarked URL with all three filters lands directly in the table — bypassing the "Pick filters" prompt. That's intentional, but verify the URL-state restoration applies all three before rendering, or you'll briefly flash the empty state on every revisit.
- **The Remove vs Mark out of stock asymmetry is confusing.** Per-row Remove clears override + qty; bulk Mark out of stock keeps overrides. If users get tripped up, surface the difference more clearly in the confirm dialog body text.
- **Export Pricing recomputes ALL inventory.** For larger inventories this isn't instant — could take seconds. The button should show a spinner immediately and the recompute should run via a job queue if it ever exceeds a few seconds, with a "completed" notification.
- **First-ever export shows everything as "changed".** With no `last_exported_price` baseline yet, every row counts as changed. The diff is overwhelming. Consider a one-time "First export — all rows are new" banner inside the preview modal.
- **Override count indicator can become inaccurate after bulk actions.** Be sure the count refetches after Clear-overrides bulk so the indicator stays in sync.
- **Cancel after recompute leaves `calculated_price` updated but not committed to TCGPlayer.** The next preview will still show the same diff. That's fine, but make sure the operator understands recompute always happens, even on Cancel.
- **The Mark-out-of-stock bulk preserves overrides.** That's by design (the user might temporarily be out and want to keep the price). But it means a "soft sell-out" trail forms over time — overrides on rows with qty=0. Periodic cleanup may eventually be warranted.
