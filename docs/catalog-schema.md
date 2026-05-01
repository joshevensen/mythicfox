# Catalog Schema

The catalog is the system of record for the cards Mythic Fox sells, the rules that determine what they're priced at, and the live stock count. It comprises four tables — `products`, `sets`, `cards`, `inventory` — plus the pricing rules layered onto products and sets.

This file is the single source of truth for catalog/inventory schema, import logic, pricing logic, and the pricing-export round-trip. The order side lives in [order-schema.md](order-schema.md).

---

## Source files

Two TCGPlayer exports and one upload feed this side of the system. All three live in [docs/assets/](assets/) as samples.

| File | Direction | Role | When |
|---|---|---|---|
| `PricingCustomExport.csv` | TCGPlayer → app | Catalog seed + market-price refresh | When new sets release or new cards are about to be added |
| `MyPricing.csv` | TCGPlayer → app | Bootstrap + drift reconciliation | One-time at launch; periodic sanity check after |
| `<exported>.csv` (MyPricing format) | app → TCGPlayer | Pricing round-trip | Whenever the seller wants TCGPlayer's listing prices refreshed |

`MyPricing.csv` and `PricingCustomExport.csv` share an identical 16-column header. They differ only in scope: MyPricing contains your live listings (~2K rows in the sample); PricingCustomExport is the full TCGPlayer catalog filtered by the seller (Category + Set Names + Conditions + Rarities + Languages + Printings — see export modal in TCGPlayer Seller Portal → Pricing tab).

### Acquisition

**PricingCustomExport** — Pricing tab → `Export Filtered CSV` → choose Category (e.g., Magic), set names (one set or all), conditions (all), rarities (all), language (English), printings (all), check "Do not compare against price" → `Export Filtered CSV`.

**MyPricing** — Pricing tab → `Export From Live`.

---

## Tables

### `products`

A product corresponds to a TCGPlayer "Product Line" (a game). Pricing rules live here and apply to every card in the game unless overridden at the set level.

|Column|Type|Notes|
|---|---|---|
|id|bigint PK||
|name|string|TCGPlayer Product Line name verbatim — `Magic`, `Lorcana TCG`, `Flesh & Blood TCG` |
|base_price|integer|cents, default 25 — pricing floor (see §Pricing logic)|
|high_price|integer|cents, default 1000 — segmentation threshold|
|market_offset|integer|cents, default 0|
|high_offset|integer|cents, default 15|
|priced_at|timestamp nullable|Last time this product's cards had their market prices refreshed by a PricingCustomExport import|
|created_at|timestamp||
|updated_at|timestamp||

### `sets`

A set is a TCGPlayer "Set Name" within a product. Pricing rules can be overridden here; null fields fall through to the product.

|Column|Type|Notes|
|---|---|---|
|id|bigint PK||
|product_id|bigint FK → products||
|name|string|TCGPlayer Set Name verbatim, e.g. `Welcome to Rathe, Unlimited` |
|base_price|integer nullable|cents — null means use product value|
|high_price|integer nullable|cents — null means use product value|
|market_offset|integer nullable|cents — null means use product value|
|high_offset|integer nullable|cents — null means use product value|
|created_at|timestamp||
|updated_at|timestamp||

Unique on `(product_id, name)`.

### `cards`

**Each row is one TCGPlayer SKU — a (product, condition) pair.** TCGPlayer assigns a distinct `tcgplayer_id` per condition variant of the same product (e.g., NM Boltyn and NM Foil Boltyn are separate `tcgplayer_id`s and separate `cards` rows).

|Column|Type|Notes|
|---|---|---|
|id|bigint PK||
|set_id|bigint FK → sets||
|tcgplayer_id|integer unique|TCGPlayer's identifier — primary upsert key for catalog imports|
|product_name|string|Card name as listed on TCGPlayer|
|number|string|Collector number; format varies by game (`BOL022`, `97/204`, `292`)|
|rarity|string|Game-specific vocabulary preserved verbatim — Magic uses `C/U/R/M/L/T`; Lorcana / Flesh & Blood use `Common`, `Uncommon`, `Rare`, `Super Rare`, `Majestic`, `Legendary`, `Enchanted`, `Token`. Do not normalize.|
|condition|string|Compound TCGPlayer condition string — see §Condition vocabulary|
|market_price|integer nullable|cents — last known TCG Market Price|
|low_price|integer nullable|cents — last known TCG Low Price|
|created_at|timestamp||
|updated_at|timestamp||

**Schema change from the prior design**: `condition` previously lived on `inventory`. It moves here because each `cards` row is a SKU (per-condition variant) — `inventory.condition` would have been redundant with `tcgplayer_id`. Inventory now keys on `card_id` alone with quantity rolled up.

### `inventory`

The seller's stock count per SKU.

|Column|Type|Notes|
|---|---|---|
|id|bigint PK||
|card_id|bigint FK → cards|**Unique** — one inventory row per card|
|quantity|integer|Total stock; non-negative|
|calculated_price|integer nullable|cents — output of the pricing algorithm. Refreshed on every pricing-export run (see §Pricing export). Never set manually.|
|override_price|integer nullable|cents — manual override. When non-null, takes precedence over `calculated_price` in exports.|
|last_exported_price|integer nullable|cents — the effective price (`COALESCE(override_price, calculated_price)`) at the moment of the most recent successful pricing export. Used as the baseline for the Inventory page's pricing-preview modal to show "what changed since last export." Set automatically by the pricing-export download step; never edited manually. Null until the first export runs.|
|created_at|timestamp||
|updated_at|timestamp||

The **effective price** for any inventory row is `COALESCE(override_price, calculated_price)`. If both are null, the row exports with an empty `TCG Marketplace Price` cell.

Acquisition-batch tracking (separate rows per purchase batch with different cost basis) is **out of scope for v1**. Re-acquiring the same card adds to the existing row.

### Condition vocabulary

The 11 strings TCGPlayer emits, observed across MyPricing and PullSheet. Stored verbatim in `cards.condition`.

|String|Physical|Finish|Edition|
|---|---|---|---|
|`Near Mint`|NM|normal|first|
|`Lightly Played`|LP|normal|first|
|`Moderately Played`|MP|normal|first|
|`Damaged`|DMG|normal|first|
|`Near Mint Foil`|NM|foil|first|
|`Lightly Played Foil`|LP|foil|first|
|`Near Mint Holofoil`|NM|holofoil|first|
|`Near Mint Cold Foil`|NM|cold foil|first|
|`Lightly Played Cold Foil`|LP|cold foil|first|
|`Near Mint Unlimited Edition Normal`|NM|normal|unlimited|
|`Near Mint Unlimited Edition Rainbow Foil`|NM|rainbow foil|unlimited|

Treat the parsed components (physical / finish / edition) as a presentation concern only. Storage uses the raw string so MyPricing exports round-trip cleanly back to TCGPlayer.

---

## Pricing logic

Pricing rules live on `products` (required) and `sets` (optional override). Each rule field on a set is independently nullable: a non-null set value wins for that field; a null set value falls back to the product's value for that same field. Fallback is **per-field**, not all-or-nothing — a set can override `high_offset` while leaving `market_offset` to inherit from the product.

### Rule fields

|Field|Description|
|---|---|
|base_price|Minimum price for any card, in cents|
|high_price|Threshold in cents that segments "high-value" from "bulk" cards|
|market_offset|Cents subtracted from the chosen input for bulk/mid-range cards|
|high_offset|Cents subtracted from the chosen input for high-value cards|

### Pricing algorithm

The algorithm takes **two inputs** — `TCG Market Price` and `TCG Low Price` — and chooses one based on the segment:

```
# Step 1: segment by TCG Market Price
if TCG Market Price > high_price:
    input = min(TCG Low Price, TCG Market Price)   # high-value: race to the top of the listings
else:
    input = max(TCG Low Price, TCG Market Price)   # bulk / mid: protect margin

# Step 2: apply offset/floor
if input > high_price:
    price = input - high_offset
elif input >= base_price:
    price = input - market_offset
else:
    price = base_price
```

`base_price` is the absolute floor — no rule can produce a price below it. `TCG Direct Low` is never used; it does not apply to non-Direct sellers.

If `TCG Low Price` is null on a card, fall back to `TCG Market Price` for both inputs (so `min(x, x) == max(x, x) == x`). If both are null, skip the card and leave `inventory.calculated_price` null — the export then emits an empty `TCG Marketplace Price` cell for it (unless `override_price` is set, which always takes precedence).

### Default product values

|Field|Default|
|---|---|
|base_price|25 (= $0.25)|
|high_price|1000 (= $10.00)|
|market_offset|0|
|high_offset|15 (= $0.15)|

### Example

Rules: `base_price=25`, `high_price=1000`, `market_offset=0`, `high_offset=15`.

|TCG Market|TCG Low|Segment|Chosen input|Result|Rule applied|
|---|---|---|---|---|---|
|$0.10|$0.05|bulk|$0.10 (max)|$0.25|base_price floor|
|$3.00|$2.50|bulk|$3.00 (max)|$3.00|market_offset (0¢)|
|$3.00|$3.50|bulk|$3.50 (max)|$3.50|market_offset (0¢)|
|$12.00|$10.50|high|$10.50 (min)|$10.35|high_offset|
|$12.00|null|high|$12.00 (fallback)|$11.85|high_offset|

---

## Import flows

### PricingCustomExport import

**When**: ad-hoc, typically when a new set releases or before adding cards from a new set/product.

**Scope of upload**: per Product Line (the seller's standard practice). The export modal supports per-set filtering, but per-product is the expected grain.

**Behavior**:

1. Persist the uploaded file via `files` (see [saas-design.md](saas-design.md)).
2. Iterate rows. For each row:
   - Upsert `products` on `name` (e.g., `Magic`). Fields other than `name` are not touched on update — pricing rules are owned by the user.
   - Upsert `sets` on `(product_id, name)`. Fields other than identity are not touched on update.
   - Upsert `cards` on `tcgplayer_id`:
     - Insert: populate `set_id`, `product_name`, `number`, `rarity`, `condition`, `market_price`, `low_price`.
     - Update: refresh `market_price` and `low_price` (parsed cents from `TCG Market Price` and `TCG Low Price`). Identity fields (`product_name`, `number`, `rarity`, `condition`) are **not** overwritten — those are stable per `tcgplayer_id`.
3. After all rows for a product are processed, set `products.priced_at = now()` for every product that was touched in this import.
4. **Pricing columns from the file other than `TCG Market Price` and `TCG Low Price` are ignored.** `Total Quantity` and `TCG Marketplace Price` are intentionally discarded — for products the seller doesn't list, they're empty anyway.

The import is **idempotent**: re-running the same file produces no logical changes (prices may refresh by a hair if TCGPlayer's market data shifted, but identity and rules are untouched).

### Column → field map (PricingCustomExport / MyPricing share this)

| CSV column | Schema destination | Notes |
|---|---|---|
| TCGplayer Id | `cards.tcgplayer_id` | Upsert key |
| Product Line | `products.name` | Upsert key |
| Set Name | `sets.name` (within product) | Upsert key |
| Product Name | `cards.product_name` | |
| Title | — | Not stored |
| Number | `cards.number` | |
| Rarity | `cards.rarity` | Verbatim |
| Condition | `cards.condition` | Verbatim — compound string |
| TCG Market Price | `cards.market_price` | Parse decimal → cents |
| TCG Direct Low | — | Not stored |
| TCG Low Price With Shipping | — | Not stored |
| TCG Low Price | `cards.low_price` | Parse decimal → cents |
| Total Quantity | `inventory.quantity` (MyPricing only — see below) | Ignored on PricingCustomExport |
| Add to Quantity | — | Not stored |
| TCG Marketplace Price | — | Not stored on import. Pricing is owned entirely by the algorithm + manual overrides; the seller's existing TCGPlayer prices are not preserved during bootstrap |
| Photo URL | — | Not stored |

### MyPricing import

`MyPricing.csv` has the same columns as PricingCustomExport but represents the seller's live TCGPlayer listings. It runs in one of two modes selected at upload time.

**Bootstrap mode** (one-time, at launch):

- Same catalog upserts as PricingCustomExport.
- Additionally upserts `inventory` rows: for each row, find the `card` by `tcgplayer_id`, then upsert `inventory` on `card_id` with `quantity = Total Quantity`. **TCG Marketplace Price is ignored** — `override_price` stays null and `calculated_price` is left unset until the first pricing export computes it. Bootstrap loads stock counts only; pricing is owned by the algorithm from day one.
- Idempotent — re-running won't double-count.

**Reconciliation mode** (periodic drift check):

- Read-only. Does not write to `cards` or `inventory`.
- For each row, compare TCGPlayer's `Total Quantity` to the local `inventory.quantity` for that `tcgplayer_id`. Compare `TCG Marketplace Price` (cents) to the local effective price (`COALESCE(override_price, calculated_price)`).
- Produce a report of discrepancies — local vs TCGPlayer, per row. The seller decides what to do (typically: investigate cause, then re-bootstrap or manually correct).
- A discrepancy in either direction (local higher or lower than TCGPlayer) is surfaced. Cards in MyPricing but missing locally are listed; cards local-only are also listed.

The two modes share the same column map; only the persistence behavior differs.

---

## Pricing export

The seller's outgoing round-trip: produce a CSV in MyPricing format that can be uploaded back into TCGPlayer to refresh listing prices.

### Trigger and behavior

When the seller clicks "Export Pricing":

1. **Recompute every inventory row's price**:
   - For each `inventory` row, look up `cards.market_price` and `cards.low_price` (cents).
   - Resolve the rule fields: take from the card's `set` if set values are non-null, else from the `product`.
   - Run the dual-input pricing algorithm. Persist the result to `inventory.calculated_price`. **Never touch `override_price`** — overrides are owned by the seller via the inventory page.
2. **Show the preview modal** (per [ux/inventory.md](ux/inventory.md)) so the seller sees changed rows before committing the download. Comparison baseline is `inventory.last_exported_price`.
3. **On Download**: emit a CSV with the exact 16-column MyPricing header (one row per inventory entry, same column order and value formats as TCGPlayer's input). Persist via `files`. Set `inventory.last_exported_price = COALESCE(override_price, calculated_price)` on every row. Triggers download.
4. **On Cancel**: close the modal. Recompute already happened, so `calculated_price` values stay updated. `last_exported_price` is **not** touched — the next preview will still show the same changes.

### Stale-data hint

Before generating, check `products.priced_at` for each product represented in inventory. If any is older than 3 days (or null), surface a non-blocking hint:

> "Magic pricing was last refreshed 5 days ago. Consider uploading a fresh PricingCustomExport for Magic before exporting."

The seller can dismiss and continue. Stale data does not block the export — it's an FYI.

### Output column map

| Output column | Source |
|---|---|
| TCGplayer Id | `cards.tcgplayer_id` |
| Product Line | `products.name` |
| Set Name | `sets.name` |
| Product Name | `cards.product_name` |
| Title | empty |
| Number | `cards.number` |
| Rarity | `cards.rarity` |
| Condition | `cards.condition` |
| TCG Market Price | `cards.market_price` (formatted decimal) |
| TCG Direct Low | empty (intentional — see note below) |
| TCG Low Price With Shipping | empty (intentional — see note below) |
| TCG Low Price | `cards.low_price` (formatted decimal) |
| Total Quantity | `inventory.quantity` |
| Add to Quantity | `0` |
| TCG Marketplace Price | `COALESCE(inventory.override_price, inventory.calculated_price)` (formatted decimal); empty if both null |
| Photo URL | empty |

`TCG Direct Low` and `TCG Low Price With Shipping` are reference data computed by TCGPlayer, not values the seller sets — TCGPlayer's MyPricing importer reads back only the seller's `TCG Marketplace Price` column. Emitting empty values for these columns is the right call. **Verify on the first real export round-trip**; if the importer rejects empty cells for these fields, backfill with a sane default (e.g. echo `TCG Low Price`) at that time.

---

## Things to consider

- **Catalog table size at scale.** With Magic, Lorcana, and Flesh & Blood seeded, `cards` will easily hit hundreds of thousands of rows. The `(set_id, product_name, number)` aggregation for the catalog page is the heaviest read query in the app — index it carefully and benchmark with a realistic-size catalog before launch. Consider a denormalized `card_groups` materialized view if the live aggregation slows down.
- **TCGPlayer can rename cards.** Rare but happens (errata, re-prints with new wording). Upsert by `tcgplayer_id` keeps the row stable, but `product_name` updates silently. If historical accuracy matters, capture the rename in an audit trail or never overwrite identity fields after first insert.
- **Pricing-rule debugging.** When a calculated price looks wrong, the dual-input algorithm path isn't obvious from the result alone (segmented by `high_price`, then offset, then floored). Log the inputs and segment decision per row during the export run so the seller can trace any surprising number.
- **Bootstrap mode is a one-shot.** Running MyPricing-bootstrap a second time after live operation is started will reset `inventory.quantity` to whatever TCGPlayer's `Total Quantity` says, potentially clobbering local edits. The Settings page should clearly distinguish "bootstrap" from "reconciliation" and require explicit confirmation for bootstrap.
- **`last_exported_price` only updates on Download.** Cancelling out of the preview modal doesn't update the baseline, so re-opening shows the same diff. Intentional, but documented for clarity.
- **PricingCustomExport file size on staging/local.** The 103MB Magic dump shouldn't be checked into git. Local dev environments need a smaller fixture (a per-set export) for fast iteration.
