# Settings

Administrative hub: pricing rules per product/set, and the audit log of every imported and exported file.

**Route**: `/settings`
**Access**: admin
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [catalog-schema.md](../catalog-schema.md), [saas-design.md](../saas-design.md)

---

## Purpose

The two things that live here:

- **Pricing rules** — base price, high-price threshold, and offsets per product (with per-set overrides). The inputs that drive the dual-input pricing algorithm in [catalog-schema.md](../catalog-schema.md).
- **File history** — a paginated audit log of every CSV/PDF imported and every export generated. Per-row download for active files.

Single scrolling page with a small TOC at the top for jumping between sections. As more administrative needs surface they get added here.

---

## Layout

```
┌────────────────────────────────────────┐
│  Settings                              │
│  Manage pricing rules and review       │
│  import/export history.                │
├────────────────────────────────────────┤
│  TOC: [Pricing Rules]  [File History]  │  ← anchor links
├────────────────────────────────────────┤
│  ## Pricing Rules                      │
│   ...                                  │
├────────────────────────────────────────┤
│  ## File History                       │
│   ...                                  │
└────────────────────────────────────────┘
```

### Page header

| Element | Content |
|---|---|
| Title | "Settings" |
| Subtitle | "Manage pricing rules and review import/export history." |
| TOC | Three anchor links: `#pricing-rules`, `#file-history`, `#seller-stats` |

The TOC is a small inline row of pill links — no fixed sidebar. As sections are added later, more links appear here.

---

## Section: Pricing Rules

Anchor: `#pricing-rules`

One sub-section per **product** (Magic, Lorcana TCG, Flesh & Blood TCG). Each product's pricing rules are editable via modal; sets are listed under their product and edited via modal too.

### Product sub-section layout

```
### Magic                                           [✏ Edit]
   base $0.25  •  high $10.00  •  market −$0.00  •  high −$0.15

   Sets (12)
   ───────────────────────────────────────────────
   Avacyn Restored
   Magic Game Night: Free-For-All
   Starter Commander Decks
   Welcome to Rathe, Unlimited           overridden
   ...
```

- **Product header row**: product name + an Edit icon (or full-row clickable). Click → product rules modal.
- **Inline rule summary**: the four rule values, formatted with `MfMoney`. Read-only at a glance.
- **Sets list**: alphabetical. Each set is a clickable row. Sets that override any product default get a small `overridden` badge to the right; sets that fully inherit show no badge.
- Long set lists: scroll within the section, no pagination. A typical product has 10–50 sets.

Set count next to "Sets" header reflects the number of sets currently in catalog for that product (i.e. distinct `sets.product_id`). Products and sets are auto-created during PricingCustomExport / MyPricing imports — there is no "Add product" or "Add set" UI.

### Product rules modal

Triggered by clicking the product header or its Edit icon.

| Element | Behavior |
|---|---|
| Title | "Magic — pricing rules" |
| Form | Four `MfMoneyInput` fields: base_price, high_price, market_offset, high_offset. All required for products (no inheritance — products are the root). |
| Buttons | Cancel / Save |

On Save: persist to `products`, close modal, refresh the product sub-section. Toast: *"Magic pricing rules saved."*

### Set rules modal

Triggered by clicking a set row.

| Element | Behavior |
|---|---|
| Title | "Welcome to Rathe, Unlimited — pricing rules" |
| Subtitle | "Overrides Flesh & Blood TCG defaults" |
| Form | Four `MfMoneyInput` fields, each **nullable** with inherited-value indicator |
| Reset all | Single button: "Reset all to product defaults" — clears all four fields to null after a confirmation |
| Buttons | Cancel / Save |

Each field's UX:

```
base_price   [ $0.50 ]    [↺ inherit]
             Flesh & Blood TCG default: $0.25
```

- The input shows the current set value if non-null; empty if inherited.
- Below the input, muted text shows the product default ("Flesh & Blood TCG default: $X.XX").
- A small "↺ inherit" link clears the field to null and restores inherited behavior.

On Save: persist non-null fields to `sets` (null fields stay null = inherited), close modal, refresh the set's row. Toast: *"Welcome to Rathe pricing rules saved."*

---

## Section: File History

Anchor: `#file-history`

Audit log of every imported and exported file. Per [saas-design.md](../saas-design.md), the `files` table records both imports and exports; imports are auto-purged after 90 days (loose) but the rows remain for audit.

### Filter panel

Standard `MfFilterPanel` above the table.

| Filter | Type | Default |
|---|---|---|
| Direction | multi-select: `import`, `export` | All |
| Purpose | multi-select: `orders`, `pricing`, `packing-slips` | All |
| Date range | `MfDatePicker` range | All time |
| Hide expired | toggle | **off** (expired files visible by default) |

Purpose values are derived from the second segment of `file_path` (per [saas-design.md §Path convention](../saas-design.md)). The dropdown options are populated from `DISTINCT` values in storage paths so new purposes appear automatically without code changes.

### Table

`MfTable` in `lazy` mode. **Page size override**: 20 rows per page (vs the standard 50 default in [ux-patterns.md](ux-patterns.md)) — File History is a sub-section, not a primary table, so it gets a more compact footprint.

| Column | Source | Sortable |
|---|---|---|
| Filename | `original_filename` | yes |
| Direction | `type` (`import` / `export`) | yes |
| Purpose | derived from `file_path` segment 2 | yes |
| Uploaded | `uploaded_at` (formatted `MMM D, YYYY h:mma`) | yes (default sort, **desc**) |
| Status | derived: "Active" if `expired_at IS NULL`, else "Expired {date}" | yes |
| (action) | — | — |

The Status column shows:

- **Active** in green-tinted text when the storage object is still present.
- **Expired Apr 12, 2026** in muted gray when the cleanup job has purged the file.

### Per-row action

Single icon at the right edge:

| Icon | Visible when | Behavior |
|---|---|---|
| ⬇ download | `expired_at IS NULL` | Generates a signed URL via the storage driver (S3-compatible signed URL in prod; direct path locally), opens the URL in a new tab. |

Expired rows have no download icon — just empty space in the actions column. The row stays for audit.

### Bulk actions

None.

---

## Section: Seller Stats Scraper

Anchor: `#seller-stats`

Health indicator + manual refresh for the daily TCGPlayer storefront scraper that maintains the `seller_stats` singleton. See [saas-design.md §Seller stats scraper](../saas-design.md) for how the job works.

### Layout

A small card showing the current state of the scraper:

```
Seller stats scraper
────────────────────────────────────────
Last successful scrape:   Apr 28, 2026 6:00am
Last attempt:             Apr 29, 2026 6:00am
Status:                   ✅ Healthy

Current values:
  Rating:                 4.9
  Reviews:                312
  Feedback comments:      3 captured

[ Refresh now ]   [ View raw data ]
```

| Field | Source |
|---|---|
| Last successful scrape | `seller_stats.scraped_at` (formatted as datetime) |
| Last attempt | `seller_stats.last_attempt_at` |
| Status | derived: `✅ Healthy` if `consecutive_failures = 0`, else `⚠️ Failed N days in a row` |
| Rating / Reviews / Comments | `seller_stats.rating`, `review_count`, `feedback.length` |

When `consecutive_failures >= 3`, this card renders with an amber border and surfaces `last_error` text — *"Selectors may have changed. Check the storefront page for redesigns."* This is the early-warning system before stale data goes public.

### Actions

- **Refresh now** — dispatches the scraper job synchronously. Useful when you spot a redesign and want to test selectors immediately. Disabled while a job is in flight.
- **View raw data** — opens a small modal with the full `seller_stats` row as JSON. Useful for debugging the parser output.

There is no manual edit form for the singleton — values come from the scraper. If the scraper is permanently broken (e.g. TCGPlayer blocks access), the fallback is the option-A approach mentioned in [saas-design.md](../saas-design.md): wire up a manual edit form here. That's a future change, not v1.

---

## Mobile layout

The page is naturally vertical and works well on phones with minor adjustments:

- **Pricing Rules sub-sections** stack the same way; the "edit" affordance becomes the entire product header (full-row tap). Set rows stay a tap-target list.
- **Pricing Rules modal and Set Rules modal** become full-screen sheets on phones. The "Inherit from {Product}" indicator sits below each input as a single line instead of beside it.
- **File History table** switches to card rows like the other heavy tables:
  ```
  ┌──────────────────────────────────────┐
  │  TCGplayer_OrderList.csv     [⬇]     │
  │  import · orders                     │
  │  Apr 14, 2026 1:41pm                 │
  │  [Active]                            │
  └──────────────────────────────────────┘
  ```
- File History's filter panel becomes a full-screen drawer; the toggle for "Hide expired" stays inline above the cards.

---

## Data

Reads:

- `products` (all rows)
- `sets` (all rows, joined to products)
- `cards` count per `set_id` to show "Sets (N)" — or `sets` count grouped by product
- `files` (paginated)
- `seller_stats` (singleton)

Writes:

- `products` updates via the product rules modal
- `sets` updates via the set rules modal
- `seller_stats` is **only** written by the scraper job; the Refresh-now button dispatches that job. No manual edit UI in v1.
- No direct writes to `files` from this page; rows are created by the import/export flows on Catalog and Inventory pages.

### Indexes (DB)

- `sets (product_id, name)` — supports the alphabetical sets list per product
- `files (uploaded_at)` — supports default sort
- `files (type)`, `files (file_path)` — supports filters

---

## States

| State | Display |
|---|---|
| First-time visit (no products yet) | Pricing Rules section: "No products yet — they'll appear after your first PricingCustomExport upload." Link to Catalog. File History: standard empty state. |
| Pricing Rules modal saving | Save button shows spinner; form disabled. |
| Pricing Rules modal save error | Inline error banner inside the modal; user can correct and retry. |
| File History empty | "No files yet — imports and exports will appear here." |
| File History filtered to zero | "No files match these filters." + Clear filters button. |
| File History download error | Toast: "Couldn't generate download URL — file may be missing from storage." |
