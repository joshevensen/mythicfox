---
id: "20-007"
title: "Implement PackingSlips PDF parser (smalot/pdfparser)"
status: pending
phase: "20-orders"
size: L
depends_on: ["20-003"]
references:
  - docs/order-schema.md#source-files
  - docs/order-schema.md#2-parse-the-four-sources
  - docs/order-schema.md#things-to-consider
---

## Goal

`PackingSlips.pdf` is the **only** source of historical per-line price data — `order_items.unit_price` and `order_items.total_price` are populated from it and from nowhere else. Each PDF page covers one order. This task parses the PDF, extracts the per-page text, and yields a structured row per (Order #, line item) with the prices attached. Without this parser, line prices stay null.

## Acceptance criteria

- [ ] `App\Services\Orders\Parsers\PackingSlipPdfParser` exposes `parse(string $absolutePath): Collection<PackingSlipLine>`.
- [ ] Uses `Smalot\PdfParser\Parser` (already installed by `00-005`).
- [ ] For each page in the PDF, the parser:
  - Extracts the order number from a header line matching `Order Number: (...)` (regex). The captured value is uppercased.
  - Extracts each line-item row, capturing: integer `quantity`, the description string, decimal `Price`, decimal `Total Price`.
  - Parses the description with `<ProductLine> - <Set> - <ProductName> - #<Number> - <Rarity> - <Condition>` (segments separated by ` - `, with the `#` prefix on the number). Returns the six fields individually.
- [ ] Each `PackingSlipLine` exposes:
  - `tcgplayer_order_number` — uppercased
  - `quantity` — integer
  - `product_line`, `set_name`, `product_name`, `number`, `rarity`, `condition` — strings (number stripped of the leading `#`)
  - `unit_price` — integer cents (parse decimal × 100, round)
  - `total_price` — integer cents
- [ ] When a page can't be parsed (no `Order Number:` header found, or the line table can't be located), the parser does not throw — it logs a warning via Laravel's logger with the page index and continues. The expectation set in `docs/order-schema.md#things-to-consider` is that the PDF is "the most fragile piece" and we want partial yields, not a hard failure.
- [ ] When no line items match for a recognized order header, the order header alone is emitted as a metadata-only entry (or skipped — pick one and document in the parser's docblock). The merge step (`20-008`) handles missing PDF lines gracefully either way.
- [ ] Pest unit tests:
  - Snapshot test against a small fixture PDF placed under `tests/Fixtures/orders/PackingSlips.pdf`. If `docs/assets/PackingSlips.pdf` exists and is small enough, copy a 1–2 page sample. Otherwise, generate a fixture from a hand-built sample.
  - At least one test asserts a multi-page PDF yields one `PackingSlipLine` per (page, line-item).
  - One test asserts a malformed page logs a warning but does not abort the parse of subsequent pages.
  - One test asserts price-to-cents conversion is exact.
- [ ] `composer test` passes.

## Implementation notes

- `smalot/pdfparser` returns text per-page via `$pdf->getPages()` and `$page->getText()`. Layout reconstruction is best-effort — text may appear in column-major order, and column boundaries are not always preserved as whitespace. Be defensive: a regex like `/^\s*(\d+)\s+(.+?)\s+\$?(\d+\.\d{2})\s+\$?(\d+\.\d{2})\s*$/m` with multiline flag is a reasonable starting point.
- Iterate page-by-page. Don't accumulate the whole PDF text into one string — per-page boundaries are how we associate lines to order numbers.
- This task **does not** match PDF lines to PullSheet line items. The merge step (`20-008`) is responsible for joining on the (Order #, six snapshot fields) key. This parser just emits structured rows.
- If the parser regularly produces zero matches for a known-good PDF, raise the priority of writing a regression test from a real production export — TCGPlayer can change the layout silently and the only defense is fixture-based tests. Note the ongoing-maintenance risk in the task's commit message.
- Money parsing: `(int) round((float) $value * 100)`. Strip leading `$`.

## Out of scope

- Joining PDF lines to PullSheet line items (`20-008`).
- Filling `unit_price`/`total_price` on existing `order_items` rows (per `docs/order-schema.md#idempotency`, the "never refill" rule means re-imports do **not** backfill prices).
- Generating packing slip PDFs (phase 70 — that's a separate, unrelated concern; this task only **reads** TCGPlayer-issued PDFs).
