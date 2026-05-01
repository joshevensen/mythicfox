---
id: "20-004"
title: "Implement OrderList CSV parser"
status: pending
phase: "20-orders"
size: M
depends_on: ["20-002"]
references:
  - docs/order-schema.md#source-files
  - docs/order-schema.md#2-parse-the-four-sources
  - docs/order-schema.md#field-source-map
  - docs/order-schema.md#date-format-conversions
  - docs/order-schema.md#csv-parsing-notes
---

## Goal

`OrderList.csv` is the **source of truth for "what orders exist"** — it covers every order in the date range including canceled ones, and it is the only required file in the four-file import batch. This task builds a pure parser that reads an `OrderList.csv` and returns a structured collection of order rows. Persistence and merging happen later (`20-008`).

## Acceptance criteria

- [ ] `App\Services\Orders\Parsers\OrderListParser` (or equivalent class) exposes a method like `parse(string $absolutePath): Collection<OrderListRow>` returning a typed DTO/array per CSV row.
- [ ] The parser is **quote-aware** — uses `League\Csv` or `fgetcsv`, not naive `str_getcsv` on raw lines. Internal commas inside quoted natural-language dates (`"Friday, 14 November 2025"`) must round-trip cleanly.
- [ ] Columns are mapped **by header name**, not position — `docs/order-schema.md#csv-parsing-notes` notes the header declares 10 columns but data rows have 9.
- [ ] Each parsed row exposes:
  - `tcgplayer_order_number` — uppercased from `Order #`
  - `tcgplayer_status` — verbatim string from `Status`
  - `buyer_name` — verbatim from `Buyer Name`
  - `order_date` — `Carbon\CarbonImmutable` parsed from the `D, j F Y` format (`Friday, 14 November 2025`)
  - `product_amount` — integer cents (parse `Product Amt` as decimal, multiply by 100, round to int)
  - `shipping_amount` — integer cents
  - `total_amount` — integer cents
  - `buyer_paid` — boolean (`True` → true, `False` → false; case-insensitive)
- [ ] On a malformed/missing column the parser raises a domain exception (`App\Exceptions\OrderImport\InvalidOrderListException`) carrying the row number and the missing/invalid header — so the import flow can surface it via the error banner.
- [ ] Pest unit test fixture covers:
  - A canonical OrderList.csv (2–3 rows including one canceled order). Use a snippet of `docs/assets/OrderList.csv` if present, or hand-craft one matching the documented column layout.
  - The 9-vs-10 header/data mismatch is tolerated.
  - Money-to-cents conversion is exact for `0.20`, `10.11`, `1234.56`.
  - Date parsing handles single-digit and two-digit days.
  - An invalid file (missing header) raises `InvalidOrderListException`.
- [ ] `composer test` passes.

## Implementation notes

- Use `League\Csv\Reader` (already a transitive dep of Laravel's Excel ecosystem; if not installed, `composer require league/csv`). It handles RFC 4180 quoted fields correctly.
- Money parsing: parse `Product Amt`, `Shipping Amt`, `Total Amt` as floats first, then `(int) round($value * 100)`. Watch for "$" prefixes if present; strip with `ltrim($s, '$')` defensively.
- Currency-string parsing should reject obviously malformed values (e.g. an empty or non-numeric `Total Amt`) with the domain exception.
- The DTO can be a readonly class (`final readonly class OrderListRow`) or a typed array; pick whichever matches existing app convention. Preferred: readonly class for IDE support.
- This task does **not** write to the DB. It returns parsed rows; the merge step (`20-008`) decides what to do with them.

## Out of scope

- ShippingExport / PullSheet / PDF parsing (separate tasks).
- The merge / upsert logic (`20-008`).
- Seller-ID validation on the order number (`20-010`).
- File persistence (`20-001` already covers the `files` row; the importer in `20-008` calls into both).
