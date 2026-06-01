# Catalog Schema

The catalog is the system of record for every card Mythic Fox sells. It follows a four-level hierarchy: **Product → Set → Card → Printing**. Each Printing is a per-finish variant of a canonical Card. Pricing rules live on Products and Sets and govern how market data is translated into listing prices.

This file is the single source of truth for catalog schema, sync logic, import logic, and pricing logic. The order side lives in [order-schema.md](order-schema.md).

---

## Tables

### `products`

A product corresponds to a game (e.g. Magic: The Gathering). Pricing rules live here and apply to every card in the game unless overridden at the set level.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | Canonical game name — `Magic`, `Lorcana TCG`, `Flesh & Blood TCG` |
| base_price | integer | cents, default 25 — pricing floor (see §Pricing logic) |
| high_price | integer | cents, default 1000 — segmentation threshold |
| market_offset | integer | cents, default 0 |
| high_offset | integer | cents, default 15 |
| priced_at | timestamp nullable | Last time this product's printings had their market prices refreshed by a PricingCustomExport import |
| created_at | timestamp | |
| updated_at | timestamp | |

### `sets`

A set is a release within a product. Pricing rules can be overridden per set; null fields fall through to the product.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| product_id | bigint FK → products | |
| name | string | Set name, e.g. `Welcome to Rathe` |
| base_price | integer nullable | cents — null means use product value |
| high_price | integer nullable | cents — null means use product value |
| market_offset | integer nullable | cents — null means use product value |
| high_offset | integer nullable | cents — null means use product value |
| cards_synced_at | timestamp nullable | Last time `catalog:sync` fetched cards for this set from the upstream source |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique on `(product_id, name)`.

### `cards`

Each row is a **canonical card** — a unique (set, name, number) identity. Cards do not carry condition or pricing data; those live on Printings.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| set_id | bigint FK → sets | |
| name | string | Card name |
| number | string | Collector number; format varies by game (`BOL022`, `97/204`, `292`) |
| rarity | string | Game-specific vocabulary preserved verbatim — Magic: `C/U/R/M/L/T`; Lorcana/FAB: `Common`, `Uncommon`, `Rare`, `Super Rare`, `Majestic`, `Legendary`, `Enchanted`, etc. |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique on `(set_id, name, number)`.

### `printings`

Each row is a per-finish variant of a canonical Card. A card can have multiple printings (non-foil, foil, rainbow-foil, cold-foil, etched, etc.). Pricing lives here.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| card_id | bigint FK → cards | |
| finish | string | Normalized finish string — `non-foil`, `foil`, `rainbow-foil`, `cold-foil`, `gold-cold-foil`, `etched` |
| tcgplayer_id | integer unique nullable | TCGPlayer's per-SKU identifier; null when not available or when a card has multiple finishes sharing one TCGPlayer ID |
| image_url | string nullable | Remote card image URL from the sync source |
| market_price | integer nullable | cents — last known TCG Market Price |
| low_price | integer nullable | cents — last known TCG Low Price |
| other_ids | json nullable | Provider-specific IDs (e.g. `{"fab_id": "uuid"}`, `{"lorcast_id": "uuid"}`) |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique on `(card_id, finish)`. `tcgplayer_id` is unique across all printings (nullable excluded).

---

## Catalog sync

Card data is populated by `catalog:sync {game?}` which calls game-specific source adapters. Each adapter implements `CatalogSyncSource`:

```
syncSets(Product $product): int       — upsert sets from upstream
syncCardsForSet(Set $set): int        — upsert cards + printings for one set
```

Supported games and their sources:

| Game | Source | Upstream |
|---|---|---|
| Magic | `ScryfallSource` | Scryfall REST API |
| Lorcana | `LorcastSource` | Lorcast REST API |
| Flesh & Blood | `FleshAndBloodSource` | fab-card-browser JSON |

After a successful `syncCardsForSet`, the set's `cards_synced_at` is stamped. Use `Set::needsCardSync()` to check whether a set's cards are stale.

Scheduled runs: Magic on Saturdays at 1 AM, Lorcana on Sundays at 1 AM, FAB daily at 3 AM.

---

## Import flows

### PricingCustomExport import

**When**: ad-hoc, typically after a catalog sync to populate/refresh market prices.

**Behavior**:

1. Persist the uploaded file via `files` (see [saas-design.md](saas-design.md)).
2. Iterate rows. For each row:
   - Upsert `products` on `name`. Pricing rule fields are not touched on update.
   - Upsert `sets` on `(product_id, name)`. Non-identity fields not touched on update.
   - Upsert canonical `cards` on `(set_id, name, number)`.
   - Upsert `printings` on `(card_id, finish)`: refresh `market_price`, `low_price`, and `tcgplayer_id`.
3. After all rows for a product are processed, set `products.priced_at = now()` for each touched product.

The import is **idempotent**: re-running the same file refreshes prices but does not duplicate rows.

### Column → field map

| CSV column | Schema destination | Notes |
|---|---|---|
| TCGplayer Id | `printings.tcgplayer_id` | Upsert key for pricing refresh |
| Product Line | `products.name` | Upsert key |
| Set Name | `sets.name` (within product) | Upsert key |
| Product Name | `cards.name` | Card identity |
| Number | `cards.number` | Card identity |
| Rarity | `cards.rarity` | Verbatim |
| Condition | `printings.finish` (derived) | e.g. `Near Mint Foil` → `foil` |
| TCG Market Price | `printings.market_price` | Parse decimal → cents |
| TCG Low Price | `printings.low_price` | Parse decimal → cents |
| Title, TCG Direct Low, TCG Low Price With Shipping, Total Quantity, Add to Quantity, TCG Marketplace Price, Photo URL | — | Not stored |

---

## Pricing logic

Pricing rules live on `products` (required) and `sets` (optional per-field override). Fallback is per-field — a set can override `high_offset` while inheriting `market_offset` from the product.

### Rule fields

| Field | Description |
|---|---|
| base_price | Minimum price for any printing, in cents |
| high_price | Threshold in cents that segments "high-value" from "bulk" printings |
| market_offset | Cents subtracted from the chosen input for bulk/mid-range printings |
| high_offset | Cents subtracted from the chosen input for high-value printings |

### Pricing algorithm

```
# Step 1: segment by TCG Market Price
if TCG Market Price > high_price:
    input = min(TCG Low Price, TCG Market Price)   # high-value: race to top of listings
else:
    input = max(TCG Low Price, TCG Market Price)   # bulk/mid: protect margin

# Step 2: apply offset/floor
if input > high_price:
    price = input - high_offset
elif input >= base_price:
    price = input - market_offset
else:
    price = base_price
```

If `TCG Low Price` is null, fall back to `TCG Market Price` for both inputs. If both are null, the calculated price is null.

### Default product values

| Field | Default |
|---|---|
| base_price | 25 (= $0.25) |
| high_price | 1000 (= $10.00) |
| market_offset | 0 |
| high_offset | 15 (= $0.15) |

### Example

Rules: `base_price=25`, `high_price=1000`, `market_offset=0`, `high_offset=15`.

| TCG Market | TCG Low | Segment | Chosen input | Result | Rule applied |
|---|---|---|---|---|---|
| $0.10 | $0.05 | bulk | $0.10 (max) | $0.25 | base_price floor |
| $3.00 | $2.50 | bulk | $3.00 (max) | $3.00 | market_offset (0¢) |
| $12.00 | $10.50 | high | $10.50 (min) | $10.35 | high_offset |
| $12.00 | null | high | $12.00 (fallback) | $11.85 | high_offset |

---

## Pricing export

The seller's outgoing round-trip: produce a CSV in MyPricing format to upload back to TCGPlayer.

### Trigger and behavior

1. Recompute every printing's calculated price using the dual-input algorithm above.
2. Show a preview modal — comparison baseline is the last exported price.
3. On Download: emit a 16-column MyPricing CSV. Stamp `last_exported_price` on each row.
4. On Cancel: recomputed `calculated_price` values persist. `last_exported_price` is not touched.

A stale-data hint appears before generating if any product's `priced_at` is older than 3 days (non-blocking).

### Output column map

| Output column | Source |
|---|---|
| TCGplayer Id | `printings.tcgplayer_id` |
| Product Line | `products.name` |
| Set Name | `sets.name` |
| Product Name | `cards.name` |
| Number | `cards.number` |
| Rarity | `cards.rarity` |
| Condition | `printings.finish` (denormalized back to TCGPlayer condition string) |
| TCG Market Price | `printings.market_price` (formatted decimal) |
| TCG Low Price | `printings.low_price` (formatted decimal) |
| TCG Marketplace Price | calculated or override price (formatted decimal); empty if both null |
| Title, TCG Direct Low, TCG Low Price With Shipping, Add to Quantity, Photo URL | empty |

---

## Things to consider

- **Catalog table size at scale.** With Magic, Lorcana, and FAB seeded, `cards` can reach hundreds of thousands of rows. Index `(set_id, name, number)` carefully and benchmark with realistic data before launch.
- **Sync staleness.** `cards_synced_at` on `Set` tracks when cards were last fetched. Use `needsCardSync()` to check; the `--force` flag on `catalog:sync` bypasses the staleness check.
- **TCGPlayer can rename cards.** Upsert by `(set_id, name, number)` means a rename creates a new canonical Card and rebinds the Printing. This is intentional — the new identity is more accurate.
- **Finish normalization.** The `PricingCustomExportImporter` derives `finish` from the TCGPlayer condition string (e.g. `Near Mint Foil` → `foil`). Sync sources use game-specific finish vocabularies mapped in each adapter.
