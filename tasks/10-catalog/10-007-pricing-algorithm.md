---
id: "10-007"
title: "Implement dual-input pricing algorithm service"
status: complete
phase: "10-catalog"
size: M
depends_on: ["10-001", "10-002", "10-003", "10-004"]
references:
  - docs/catalog-schema.md#pricing-logic
  - docs/catalog-schema.md#pricing-algorithm
  - docs/catalog-schema.md#example
---

## Goal

Encode the dual-input pricing algorithm exactly as specified in `docs/catalog-schema.md#pricing-algorithm`: take `TCG Market Price` and `TCG Low Price` (cents), segment by `high_price`, choose `min` or `max` of the two inputs, apply the matching offset, and floor at `base_price`. This service is the single source of truth for `inventory.calculated_price` and is consumed by both the export recompute (`10-009`) and ad-hoc preview/debug tooling.

## Acceptance criteria

- [x] A pure-function service (e.g. `App\Services\Catalog\PriceCalculator` with a static `calculate(int $marketPrice, ?int $lowPrice, PricingRules $rules): ?int`) implements the algorithm verbatim.
- [x] Algorithm matches the doc step-by-step:
  - Segment by `TCG Market Price > high_price` → high-value uses `min(low, market)`; bulk uses `max(low, market)`.
  - Apply offset: input above `high_price` → `input - high_offset`; input ≥ `base_price` → `input - market_offset`; otherwise → `base_price`.
- [x] Null-handling:
  - If `low_price` is null, fall back to `market_price` for both inputs (so `min(x, x) == max(x, x) == x`).
  - If `market_price` is null (regardless of `low_price`), return null. The doc says "if both are null skip the row" — the simplest expression of the rule is "no market price → no calculated price."
- [x] A `PricingRules` value object (or DTO) holds the four cents fields and is constructed via a resolver helper that takes a `Card` (or its `Set`) and returns the effective rules — set values override product values **fully** when any single set field is non-null. *Re-read the doc carefully here:* the spec says "Set rules take **full** precedence when defined — there is no partial inheritance. Null values on a set fall back to the product's values entirely." Implement this as: for **each individual field**, use the set's value if non-null, else the product's value. (This matches the schema where each field is independently nullable on `sets`. The "full precedence" wording in the doc is about the absence of partial-inheritance hooks like "use 75% of product offset" — not about all-or-nothing per row. Confirm against the example table — every example uses product defaults — and document the chosen interpretation in the commit message and the resolver's docblock.)
- [x] Pest unit tests cover **every row of the example table** in `docs/catalog-schema.md#example` and assert the exact expected output cents.
- [x] Additional Pest tests cover: null `low_price` fallback, null `market_price` returns null, set overrides shadow product values per the chosen resolver semantics, base-price floor behavior at exact boundary (`input == base_price`), `high_price` segment boundary (`market == high_price` → bulk; `market > high_price` → high).
- [x] Service does NOT mutate any model. It's a calculator. The caller (`10-009`) writes the result.
- [x] `composer test` passes.

## Implementation notes

- The pricing-rule resolution semantics are the most likely point of confusion in this whole phase. Read `docs/catalog-schema.md#pricing-logic` *and* the example table *and* the schema's nullable-per-field set columns together. The schema design (each field independently nullable) only makes sense under per-field fallback. Lock that interpretation in via the docblock and unit tests so the next agent doesn't re-litigate it.
- All math is integer cents. No floats anywhere. Subtracting offsets cannot produce a negative — the floor branch handles low values, but assert in the calculator that the result is never negative as a defensive check.
- Per the doc's "Things to consider" section, log the inputs and segment decision per row when invoked from the export run (`10-009` will pass a logger). For unit tests, no logging is required.
- TCG Direct Low is never used. Don't add a parameter for it.

## Out of scope

- The actual recompute over all inventory rows — `10-009`.
- Per-set rule editing UI — phase 60.
- Bulk simulation tooling ("preview prices if I change `high_offset` to 25") — not in v1.
