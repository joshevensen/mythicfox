# Order Schema

Orders and order items are **denormalized historical snapshots** — they capture exactly what TCGPlayer billed and shipped, independent of any later changes to the catalog or inventory. The order side of the system is one-way (TCGPlayer → app); there is no outbound round-trip equivalent to the pricing export.

This file is the single source of truth for order schema, the four-source import logic, and idempotency rules. Catalog/inventory lives in [catalog-schema.md](catalog-schema.md).

---

## Source files

Four TCGPlayer outputs feed the order side. All four live in [docs/assets/](assets/) as samples.

| File | Grain | What it uniquely provides |
|---|---|---|
| `OrderList.csv` | One row per order (date range filtered) | Status, totals, buyer name (combined), natural-language order date — covers **all** orders including canceled |
| `ShippingExport.csv` | One row per shippable order | Mailing address (split first/last name), ISO order date, tracking, carrier — covers a subset |
| `PullSheet.csv` | One row per (product, condition) line item | The only CSV with line-item data — Order # + qty per pair encoded in `Order Quantity` field |
| `PackingSlips.pdf` | One page per order | The only source of **per-line price at time of sale** — needed for `order_items.unit_price` and `total_price` |

All four are uploaded as a single batch in the same import. The PDF is the **only** source of historical line prices; without it, `unit_price` and `total_price` stay null.

### Acquisition

From TCGPlayer Seller Portal → **Orders** tab. Default filter is "last 90 days" — fine if exports happen often.

1. Click **`Export Orders`** (top-right). Downloads `OrderList.csv`. No row selection needed.
2. Tick the master checkbox to **select all rows**. The black action bar appears with three buttons:
   - **`Pull Sheet`** → `PullSheet.csv`
   - **`Packing Slip`** → `PackingSlips.pdf`
   - **`Export Shipping`** → `ShippingExport.csv`

---

## Tables

### `orders`

|Column|Type|Notes|
|---|---|---|
|id|bigint PK||
|tcgplayer_order_number|string unique|TCGPlayer's `Order #`. **Upsert key.** Format: hyphen-separated hex string, e.g. `623394E9-23CAFE-565FC`. The first segment is the **seller ID** (matches the path slug at the end of the TCGPlayer storefront URL — `https://www.tcgplayer.com/sellers/Mythic-Fox-Games/{seller_id}`). For Mythic Fox Games this prefix is `623394E9` (case-insensitive). Imported order numbers should start with this prefix; rejecting non-matching rows is a useful sanity check against accidentally importing someone else's exports.|
|tcgplayer_status|string|TCGPlayer's `Status` value verbatim — `Completed - Paid`, `Canceled`, or whatever else TCGPlayer emits. No mapping or enum on our side|
|buyer_firstname|string nullable|From ShippingExport. Null when only OrderList is available (e.g. canceled order with no shipping data)|
|buyer_lastname|string nullable|Same|
|buyer_name|string|From OrderList — combined name. Always populated|
|address1|string nullable||
|address2|string nullable||
|city|string nullable||
|state|string nullable|2-letter|
|postal_code|string nullable|May include zip+4 (`75569-3016`)|
|country|string nullable|E.g. `US`|
|order_date|date|ISO from ShippingExport when present, else parsed from OrderList's natural-language date|
|shipping_method|string nullable|E.g. `Standard (7-10 days)`|
|item_count|integer nullable|From ShippingExport|
|product_weight|decimal nullable|Pounds, from ShippingExport|
|product_amount|integer|cents — `Product Amt` from OrderList|
|shipping_amount|integer|cents — `Shipping Amt` from OrderList|
|total_amount|integer|cents — `Total Amt` from OrderList|
|buyer_paid|boolean|From OrderList|
|tracking_number|string nullable||
|carrier|string nullable||
|imported_at|timestamp||
|created_at|timestamp||
|updated_at|timestamp||

**Schema change from the prior design**: added `buyer_name`, `product_amount`, `total_amount`, `buyer_paid`, and made shipping-related fields nullable so canceled orders (which lack ShippingExport rows) can be recorded. `value_of_products` and `shipping_fee_paid` are renamed to align with OrderList's column naming (`product_amount`, `shipping_amount`) and converted to cents at import.

### `order_items`

Fully denormalized — no foreign keys to catalog tables. The fields below are the snapshot at time of sale; later catalog renames or condition reclassifications never touch existing rows.

|Column|Type|Notes|
|---|---|---|
|id|bigint PK||
|order_id|bigint FK → orders||
|product_line|string|Snapshot, e.g. `Magic`|
|set_name|string|Snapshot|
|product_name|string|Snapshot|
|number|string|Collector number snapshot|
|rarity|string|Snapshot|
|condition|string|Compound TCGPlayer condition string — see [catalog-schema.md §Condition vocabulary](catalog-schema.md)|
|quantity|integer|Quantity sold of this line|
|unit_price|integer nullable|cents — from PackingSlips PDF; null if PDF not uploaded with batch|
|total_price|integer nullable|cents — from PackingSlips PDF|
|tcgplayer_sku_id|integer nullable|TCGPlayer's `SkuId` from PullSheet. Stored for traceability; not used as a key|
|created_at|timestamp||
|updated_at|timestamp||

`order_items` are immutable once created — see §Idempotency.

---

## Field source map

For each `orders` column, which source file populates it. ShippingExport wins where it overlaps with OrderList.

| Schema field | Primary source | Fallback / notes |
|---|---|---|
| `tcgplayer_order_number` | OrderList `Order #` | also in ShippingExport, PullSheet, PDF — used as the join key across all four |
| `tcgplayer_status` | OrderList `Status` | verbatim string |
| `buyer_firstname` | ShippingExport `FirstName` | null if no ShippingExport row |
| `buyer_lastname` | ShippingExport `LastName` | null if no ShippingExport row |
| `buyer_name` | OrderList `Buyer Name` | always present |
| `address1`–`country` | ShippingExport | null if no ShippingExport row |
| `order_date` | ShippingExport `Order Date` (ISO) | parse OrderList's `Friday, 14 November 2025` if ShippingExport row missing |
| `shipping_method` | ShippingExport `Shipping Method` | also `Shipping Type` exists in OrderList (less specific); prefer ShippingExport |
| `item_count` | ShippingExport `Item Count` | null if no ShippingExport row |
| `product_weight` | ShippingExport `Product Weight` | null if no ShippingExport row |
| `product_amount` | OrderList `Product Amt` × 100 → cents | |
| `shipping_amount` | OrderList `Shipping Amt` × 100 → cents | |
| `total_amount` | OrderList `Total Amt` × 100 → cents | |
| `buyer_paid` | OrderList `Buyer Paid` (`True`/`False`) | |
| `tracking_number` | ShippingExport `Tracking #` | often empty |
| `carrier` | ShippingExport `Carrier` | often empty |
| `imported_at` | wall clock at import | |

For each `order_items` column:

| Schema field | Source |
|---|---|
| `product_line` | PullSheet `Product Line` |
| `set_name` | PullSheet `Set` |
| `product_name` | PullSheet `Product Name` |
| `number` | PullSheet `Number` |
| `rarity` | PullSheet `Rarity` |
| `condition` | PullSheet `Condition` |
| `quantity` | parsed from PullSheet `Order Quantity` (the per-order qty after splitting) |
| `unit_price` | PackingSlips PDF — line "Price" column for the matching description |
| `total_price` | PackingSlips PDF — line "Total Price" column |
| `tcgplayer_sku_id` | PullSheet `SkuId` |

---

## Import flow

The order import takes the four files as a batch. The flow:

### 1. Persist files

Save each file via the `files` table.

### 2. Parse the four sources

- **OrderList** — quote-aware CSV parser. Be lenient about column count: the header declares 10 columns but data rows have 9 (the `Carrier Information` column is omitted — map columns by header, not by position).
- **ShippingExport** — quote-aware CSV parser, 17 columns.
- **PullSheet** — quote-aware CSV parser, 11 columns. Parse the `Order Quantity` field as a pipe-delimited list of `<Order #>:<qty>` pairs (separator: ` | `, with spaces). Each pair becomes one prospective line item.
- **PackingSlips PDF** — extract per-page text (e.g. `pdftotext` or `smalot/pdfparser`). For each page, capture:
  - The order number (from the `Order Number: …` header).
  - Each line-item row: `quantity`, the description string, `Price` (decimal), `Total Price` (decimal).
  - Parse the description string with the format `<ProductLine> - <Set> - <ProductName> - #<Number> - <Rarity> - <Condition>` (each segment separated by ` - `, with `#` prefix on the number).

Build an in-memory map keyed by `Order #` joining all four sources.

### 3. Upsert orders

For each `Order #` present in OrderList:

- If the order does not exist locally: insert a new `orders` row, populating from OrderList + ShippingExport (where present) + parsed PDF order metadata. Then create `order_items` (next step).
- If the order exists locally: update mutable fields only — `tcgplayer_status`, `tracking_number`, `carrier`. Do **not** touch `order_items`, addresses, totals, dates, or buyer info. (Historical snapshot — once captured, immutable except for fulfillment state.)

Orders that appear in ShippingExport or PullSheet but not in OrderList are skipped with a warning. OrderList is the source of truth for "what orders exist."

### 4. Create order_items (new orders only)

For each new order, look up matching PullSheet rows (via the parsed `Order Quantity` index) and create one `order_items` row per (Order #, line). Then enrich with PDF prices:

- For each newly-created `order_items` row, find the matching PDF line by joining on `(order_id, product_line, set_name, product_name, number, rarity, condition)`. All six fields must match exactly (TCGPlayer emits identical strings across PullSheet and PDF descriptions).
- Set `unit_price` and `total_price` from the matched PDF line. If no PDF line matches (PDF wasn't uploaded, or matching failed), leave them null and log a warning.

### Idempotency

| Re-import scenario | Behavior |
|---|---|
| Same batch uploaded twice | No-op for orders, no-op for order_items |
| Status changes upstream (e.g. order canceled after first import) | `tcgplayer_status` updates in place |
| Tracking added after first import | `tracking_number` and `carrier` update in place |
| Order in OrderList but not in earlier import (newer order) | Inserted fresh, line items created |
| Order had null line prices, later batch includes the PDF | **Line prices stay null.** Re-import does not refill `order_items` once they exist. Workaround: include the PDF in every batch from the start. |

The "never refill" rule is intentional — it preserves the immutability of historical snapshots. The trade-off is acceptable because:

- The recommended workflow uploads all four files together every time.
- A null `unit_price` is recoverable manually if it matters for a specific report.

---

## Status

`orders.tcgplayer_status` stores TCGPlayer's `Status` value verbatim — no mapping, no enum, no app-side lifecycle. Observed values: `Completed - Paid`, `Canceled`. Whatever TCGPlayer emits is what we store.

The two practical decisions that consume status are explicit string comparisons at the call site:

- **"Which orders need a packing slip?"** → `tcgplayer_status = 'Completed - Paid'` AND `tracking_number IS NULL`.
- **"Skip canceled orders for inventory decrement"** (whenever inventory decrement is implemented) → skip rows where `tcgplayer_status = 'Canceled'`.

If TCGPlayer ever introduces new status strings, queries that filter by specific values will need updating, but no schema change is required.

---

## Date format conversions

All dates are stored as ISO `YYYY-MM-DD`.

| Source | Format | Conversion |
|---|---|---|
| ShippingExport `Order Date` | `YYYY-MM-DD` (ISO) | use as-is |
| OrderList `Order Date` | `<DayName>, <D> <MonthName> <YYYY>` (e.g., `Friday, 14 November 2025`) | parse with `D, j F Y` (PHP) or equivalent. Quoted in CSV — internal comma is part of the field |
| PullSheet `Set Release Date` | `MM/DD/YYYY HH:MM:SS` | not stored; if it ever is, convert to ISO |
| PDF "Order Date" | `MM/DD/YYYY` US format | not used — ShippingExport / OrderList are authoritative |

---

## CSV parsing notes

- Use a quote-aware parser (e.g. PHP `League\Csv` or `fgetcsv`). Naive comma-splitting breaks on OrderList's natural-language order dates which contain internal commas.
- All CSV fields are quoted in observed exports, including numeric and boolean fields.
- Map columns by header name, not position. OrderList's data rows have 9 fields; the header declares 10.
- File encoding: UTF-8.

---

## Open questions

1. **PDF parsing robustness.** Long card descriptions might wrap to multiple lines in the PDF, breaking single-line regex matching. Verify with edge cases (very long card names, multiple sets per line) before relying on the matcher in production.
2. **Inventory decrement on order import.** Does importing an order automatically decrement `inventory.quantity` for each `order_items` line? If yes, the implementation needs a (cards-by-snapshot-fields) lookup since `order_items` has no FK. If no, inventory drifts from reality. Decision deferred to feature build time.
