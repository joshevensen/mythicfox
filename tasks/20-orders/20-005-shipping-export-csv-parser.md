---
id: "20-005"
title: "Implement ShippingExport CSV parser"
status: pending
phase: "20-orders"
size: M
depends_on: ["20-002"]
references:
  - docs/order-schema.md#source-files
  - docs/order-schema.md#2-parse-the-four-sources
  - docs/order-schema.md#field-source-map
  - docs/order-schema.md#date-format-conversions
---

## Goal

`ShippingExport.csv` is the only source of mailing-address fields, ISO order date, tracking number, carrier, and split first/last buyer name. It covers the **shippable subset** of orders — canceled orders typically have no row here. The parser reads the file and returns one structured row per order; the merge step in `20-008` joins it onto OrderList.

## Acceptance criteria

- [ ] `App\Services\Orders\Parsers\ShippingExportParser` exposes `parse(string $absolutePath): Collection<ShippingExportRow>`.
- [ ] Quote-aware parser; columns mapped by header name (17 columns per `docs/order-schema.md#source-files`).
- [ ] Each parsed row exposes:
  - `tcgplayer_order_number` — uppercased from `Order #`
  - `buyer_firstname` — from `FirstName`
  - `buyer_lastname` — from `LastName`
  - `address1`, `address2`, `city`, `state`, `postal_code`, `country` — verbatim, with empty strings normalized to null
  - `order_date` — `CarbonImmutable` parsed as ISO `Y-m-d` from `Order Date`
  - `shipping_method` — from `Shipping Method`
  - `item_count` — integer from `Item Count`
  - `product_weight` — float (decimal pounds) from `Product Weight`
  - `tracking_number` — from `Tracking #` (null if empty)
  - `carrier` — from `Carrier` (null if empty)
- [ ] On malformed input the parser raises `App\Exceptions\OrderImport\InvalidShippingExportException` with the offending row number and column.
- [ ] Pest unit tests cover:
  - A canonical ShippingExport.csv (use `docs/assets/ShippingExport.csv` snippet if present, else hand-crafted).
  - Empty `Tracking #` and `Carrier` produce null, not empty string.
  - Postal codes with `zip+4` (`75569-3016`) preserved verbatim.
  - 2-letter state preserved.
  - Missing-header and missing-required-column cases raise the domain exception.
- [ ] `composer test` passes.

## Implementation notes

- Use `League\Csv\Reader` with `setHeaderOffset(0)`.
- The `state` field is documented as 2-letter; do **not** uppercase or otherwise transform — pass through verbatim. (TCGPlayer is consistent.)
- `product_weight` stays a float here; the `orders.product_weight` column is `decimal(8,2)` and Eloquent handles the cast on insert.
- Don't try to resolve missing rows ("order in OrderList but not in ShippingExport") here — that's the merge step's responsibility.

## Out of scope

- Any other parser (separate tasks).
- Merge / upsert (`20-008`).
- Address validation — the field is whatever TCGPlayer says it is.
