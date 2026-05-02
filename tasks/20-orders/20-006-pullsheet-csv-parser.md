---
id: "20-006"
title: "Implement PullSheet CSV parser (with `Order Quantity` split)"
status: complete
phase: "20-orders"
size: M
depends_on: ["20-003"]
references:
  - docs/order-schema.md#source-files
  - docs/order-schema.md#2-parse-the-four-sources
  - docs/order-schema.md#field-source-map
---

## Goal

`PullSheet.csv` is the only source of line-item data. Each row is one (product, condition) pair across **possibly multiple** orders, with the per-order quantity encoded in the `Order Quantity` field as a pipe-delimited list of `<Order #>:<qty>` pairs. The parser reads the file, splits the `Order Quantity` field, and emits one structured prospective-line-item per (Order #, product, condition).

## Acceptance criteria

- [x] `App\Services\Orders\Parsers\PullSheetParser` exposes `parse(string $absolutePath): Collection<PullSheetLineItem>`.
- [x] Quote-aware parser; columns mapped by header name (11 columns).
- [x] The `Order Quantity` cell (e.g. `623394E9-23CAFE-565FC:2 | 623394E9-X-Y:1`) is split on the literal separator `' | '` (space, pipe, space). Each segment is then split on `:` into order-number and integer quantity. The parser emits one `PullSheetLineItem` per pair.
- [x] Each `PullSheetLineItem` exposes:
  - `tcgplayer_order_number` — uppercased
  - `quantity` — integer
  - `product_line` — from `Product Line`
  - `set_name` — from `Set Name` (or `Set` — match whichever header the actual file uses; verify against `docs/assets/PullSheet.csv`)
  - `product_name` — from `Product Name`
  - `number` — from `Number`
  - `rarity` — from `Rarity`
  - `condition` — from `Condition` (full compound condition string verbatim)
  - `tcgplayer_sku_id` — integer from `SkuId` (or `Sku Id` — verify header)
- [x] Invalid `Order Quantity` syntax (no `:`, non-integer qty, etc.) raises `App\Exceptions\OrderImport\InvalidPullSheetException` carrying the row number and the bad cell value.
- [x] Pest unit tests cover:
  - A canonical PullSheet.csv with at least one row whose `Order Quantity` contains 2+ orders, asserting both line items emit.
  - Single-order `Order Quantity` (no `|`) parses correctly.
  - Whitespace tolerance: parser accepts `A:1|B:2` and `A:1 | B:2` equivalently.
  - Compound condition strings (e.g. `Near Mint Foil`, `Lightly Played - RF`) preserved verbatim — see `catalog-schema.md#condition-vocabulary`.
  - `tcgplayer_sku_id` is integer (not string); empty values null.
  - Malformed `Order Quantity` raises the domain exception.
- [x] `composer test` passes.

## Implementation notes

- Reuse `League\Csv\Reader` like the other parsers.
- The split rule documented in `docs/order-schema.md#2-parse-the-four-sources` is `' | '` (with surrounding spaces). Be tolerant of `|` without spaces — strip whitespace per segment after splitting.
- The exact header for the set column may be `Set Name` per other docs, or `Set` per the order-schema doc — check `docs/assets/PullSheet.csv` and use whichever the file actually uses. If the asset isn't present, default to `Set` and add a TODO comment.
- This parser does **not** look up against the catalog or check inventory — it just emits prospective line items keyed by order number. The merge step (`20-008`) joins them onto orders; the decrement step (`20-009`) hits inventory.

## Out of scope

- Any other parser (separate tasks).
- The PDF price enrichment that completes `unit_price`/`total_price` (`20-007` parses the PDF, `20-008` joins it).
- Inventory matching (`20-009`).
