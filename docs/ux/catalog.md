# Catalog

Browse every card the system knows about. Canonical cards are synced from game-specific sources (`catalog:sync`) and can have their market prices refreshed by uploading a TCGPlayer PricingCustomExport CSV. One row per canonical card; expand to see per-finish Printing details.

**Route**: `/catalog`
**Access**: admin
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [catalog-schema.md](../catalog-schema.md)

---

## Purpose

Lookup and browse view for the operator. Primary use cases:

- Browse the full catalog across all supported games (Magic, Lorcana, Flesh & Blood)
- Look up a specific card by name, number, or set
- Expand a row to see per-finish Printing details (finish, TCGPlayer ID, market price, low price)
- Trigger a TCGPlayer PricingCustomExport CSV import to refresh market prices
- Monitor pricing staleness per product

---

## Layout

### Page header

| Element | Behavior |
|---|---|
| Title | "Cards" |
| Stale-data indicator | Per-product inline text: e.g. "Magic refreshed 2 days ago". Highlighted in amber when any product's `priced_at` is older than 3 days or null. |
| **Import catalog** button | Opens the GlobalImportModal on the catalog tab. |

### Filter panel

Collapsible (`MfFilterPanel`), sits above the table.

| Filter | Type | Default |
|---|---|---|
| Product | single-select dropdown | "All products" |
| Set | multi-select, chained to Product | "All sets" |

Set filter options update when Product changes. Any Set selections not in the new Product are dropped with a toast: *"Removed N set filters not in {Product}"*.

Filter state lives in the URL.

### Table

Sortable, paginated, with expandable rows.

#### Row columns

One row per canonical Card.

| Column | Source | Sortable |
|---|---|---|
| Card Name | `cards.name` | yes — default sort, ascending |
| Number | `cards.number` | yes |
| Set Name | `sets.name` | yes |
| Rarity | `cards.rarity` | yes |

#### Expand row

Click the row expand toggle to reveal a sub-table of Printings for that card.

| Sub-column | Source | Notes |
|---|---|---|
| Finish | `printings.finish` | Normalized — e.g. `Non Foil`, `Foil`, `Rainbow Foil` |
| TCGplayer ID | `printings.tcgplayer_id` | Monospace (`MfMonospaceId`); `—` if null |
| Market | `printings.market_price` | Formatted as currency; `—` if null |
| Low | `printings.low_price` | Formatted as currency; `—` if null |

---

## Interactions

### Filtering

Standard `MfFilterPanel` behavior. Product and Set filters are URL-persisted and chained: Set options are scoped to the selected Product. Stale Set selections are dropped silently with a toast on Product change.

### Row expand

Click the row expand toggle (or anywhere on the mobile card) to toggle the Printing sub-table inline. No navigation or modal.

### Import

Click **Import catalog** to open the GlobalImportModal on the catalog tab. This accepts a TCGPlayer PricingCustomExport CSV and queues an import job that refreshes market prices on Printings. While in flight, the button shows an "Importing…" state.

---

## States

| State | Display |
|---|---|
| Empty (no catalog rows ever) | "No cards in catalog." + prompt to run `php artisan catalog:sync` + **Import catalog** button. |
| Empty (filters return zero rows) | "No cards match these filters." + Clear filters button. |
| Loading | Skeleton rows per the standard pattern. |
| During import | Import button shows "Importing…"; existing rows unchanged until refresh. |
| Stale data | Per-product staleness text in amber when `priced_at` is null or older than 3 days. |

---

## Mobile layout

Standard responsive behavior per [ux-patterns.md §Responsive behavior](ux-patterns.md). Mobile card layout:

```
┌──────────────────────────────────────┐
│  Agatha's Soul Cauldron         ▸    │
│  #234  ·  Wilds of Eldraine          │
│  Mythic                              │
└──────────────────────────────────────┘
```

Tap anywhere on the card to expand. Sub-rows render as a table inside the expanded card.

---

## Things to consider

- **Catalog sync vs. pricing import are separate concerns.** `catalog:sync` populates Cards and Printings from upstream APIs. PricingCustomExport import refreshes market prices on existing Printings. The two flows are independent.
- **`tcgplayer_id` is null for multi-finish cards.** When a game source reports a card without a per-finish TCGPlayer ID, `tcgplayer_id` is stored as null on all Printings for that card. This is expected and displayed as `—` in the table.
- **Sync staleness.** `cards_synced_at` on Set tracks the last sync. The `catalog:sync` command respects this; use `--force` to bypass.
