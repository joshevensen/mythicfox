---
id: "10-008"
title: "Implement pricing-rules resolver and inventory recompute service"
status: pending
phase: "10-catalog"
size: M
depends_on: ["10-007"]
references:
  - docs/catalog-schema.md#pricing-logic
  - docs/catalog-schema.md#trigger-and-behavior
  - docs/catalog-schema.md#stale-data-hint
---

## Goal

Wrap `PriceCalculator` (`10-007`) with the orchestration layer that the export flow needs: walk every `inventory` row, resolve effective pricing rules from set/product fallback, run the calculator, persist `calculated_price`, and surface the stale-data hint (per-product `priced_at` older than 3 days). This service is what `10-009`'s "recompute" step calls.

## Acceptance criteria

- [ ] A service class (e.g. `App\Services\Catalog\InventoryRecomputeService`) exposes a single `recompute()` method that:
  - Iterates every `inventory` row joined to its `card → set → product`.
  - Resolves the effective `PricingRules` per row (set values override product values per the resolver semantics fixed in `10-007`).
  - Calls `PriceCalculator::calculate()`.
  - Persists the result to `inventory.calculated_price`. Leaves `override_price` and `last_exported_price` untouched.
  - Returns a result DTO summarizing rows processed, rows with non-null result, rows that produced null (both input prices missing), and any per-product `priced_at` ages.
- [ ] Recompute is performed in chunks (`Inventory::query()->lazyById()` or `chunk()`) and wrapped in transactions per chunk.
- [ ] A separate `StalePricingChecker` (or a method on the recompute service) returns the list of products in inventory whose `priced_at` is null OR older than 3 days. Output is structured (product name + age in days + count of inventory rows for that product) — phase 60 will render this as the inventory-page hint per `docs/catalog-schema.md#stale-data-hint`.
- [ ] `inventory.calculated_price` correctly becomes null when both `card.market_price` and `card.low_price` are null (per `10-007`'s contract).
- [ ] Pest feature tests cover:
  - A seeded mix of cards with varying market/low prices and a mix of inventory rows produces the expected `calculated_price` for each (cross-checked against the algorithm's example table).
  - Set-level override fields shadow product fields per the resolver semantics — at least one test case where a set has a different `base_price` than its product, and inventory rows in that set use the set's value.
  - `override_price` is never touched by recompute (assert before/after).
  - Stale-pricing checker correctly buckets a product with `priced_at = now() - 5 days` as stale and one with `priced_at = now() - 1 day` as fresh.
- [ ] Recompute is idempotent: running it twice in a row with no upstream price changes produces identical `calculated_price` values.
- [ ] `composer test` passes.

## Implementation notes

- This is the orchestration layer; keep the math in `PriceCalculator`. Don't duplicate any algorithm logic here.
- Eager-load `card.set.product` to avoid N+1. With large inventories this matters.
- The 3-day staleness threshold is a constant (`Stale_Threshold_Days = 3`) — keep it on the service class so phase 60 can read it for its UI copy.
- Recompute does **not** trigger automatically when set/product rules change. Per the doc's flow, recompute happens at "Export Pricing" click time. If we later want a manual "recompute now" button, expose the service via an Artisan command — but that's UI scope, not this task.

## Out of scope

- The CSV export step itself — `10-009`.
- The preview modal — phase 60 (inventory page).
- Triggering recompute from set/product edits — explicitly not in the doc's flow.
- Async / queued recompute — synchronous is fine until proven slow.
