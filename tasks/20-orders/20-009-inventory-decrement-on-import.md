---
id: "20-009"
title: "Inventory decrement service for new order line items"
status: complete
phase: "20-orders"
size: M
depends_on: ["20-003", "phase:10-catalog"]
references:
  - docs/order-schema.md#5-decrement-inventory-new-orders-only-non-cancelled
  - docs/order-schema.md#status
  - docs/order-schema.md#things-to-consider
  - docs/catalog-schema.md#inventory
---

## Goal

When a new order is imported, its line items consume catalog inventory. The decrement is the **only** place orders touch the catalog side. It must:

- Skip canceled orders entirely (no decrement).
- Match each line item against the catalog by snapshot fields.
- Floor at zero (no negative inventory).
- Run only on **newly-created** line items so re-imports never double-decrement.
- Surface unmatched lines so the operator can investigate.

## Acceptance criteria

- [x] `App\Services\Orders\InventoryDecrementer` exposes `decrement(Order $order, Collection<OrderItem> $newItems): InventoryDecrementResult` returning counts: matched-and-decremented, unmatched (no card found), and unmatched (no inventory row).
- [x] If `$order->tcgplayer_status === 'Canceled'`, the service returns immediately with all counters at zero. **No decrement** for any line item on a canceled order. Per `docs/order-schema.md#5-decrement-inventory-new-orders-only-non-cancelled`.
- [x] For each `OrderItem` in `$newItems`:
  1. **Find the catalog row** by joining on:
     - `products.name = order_items.product_line`
     - `sets.name = order_items.set_name` (within that product)
     - `cards.product_name = order_items.product_name`
     - `cards.number = order_items.number`
     - `cards.condition = order_items.condition`
     The seven-field match must be exact (matching the documentation's "snapshot fields" list).
  2. **Find the inventory row** by `inventory.card_id = cards.id`.
  3. **Decrement** with `inventory.quantity = MAX(0, inventory.quantity - $orderItem->quantity)`. Use a single SQL `UPDATE ... SET quantity = GREATEST(0, quantity - ?) WHERE card_id = ?` so concurrent imports can't race.
  4. **No match** (no `cards` row, or no `inventory` row): log a warning with the order number + the snapshot fields, increment the appropriate unmatched counter, do **not** abort.
- [x] The service is idempotent at the order level — calling it twice with the same `$newItems` list would double-decrement, but this is prevented at the caller level: `20-008` only invokes it for newly-inserted line items, never for existing ones.
- [x] Pest feature tests cover:
  - A new order with all line items matching → inventory decrements, counters reflect.
  - Decrement floors at zero when `quantity > inventory.quantity`.
  - Canceled order → zero decrements regardless of line items.
  - Unknown product (no catalog match) → unmatched counter increments, others decrement.
  - Card with no inventory row → counter increments, others decrement.
  - Two concurrent calls to `decrement()` against the same card row produce a final value floored at zero (use `DB::transaction()` + `lockForUpdate()` in the test setup).
- [x] `composer test` passes.

## Implementation notes

- Phase 10 is responsible for the `products`, `sets`, `cards`, and `inventory` tables. This task assumes those exist with the field names used in the match — verify against `docs/catalog-schema.md#inventory` and the actual phase-10 migrations once they're in place. If the catalog schema diverges (e.g. `cards.product_name` is named something else), open a follow-up rather than mutating either schema in this task.
- `cards.condition` per `docs/catalog-schema.md#condition-vocabulary` stores the same compound TCGPlayer string that `order_items.condition` does — so an exact `=` match is correct. If the catalog ever splits condition into base + finish suffix, this match needs revisiting.
- Per `docs/order-schema.md#things-to-consider`, the no-match counter is a leading indicator of catalog drift. The result object is consumed by the import-result toast in phase 60 (`"Imported N orders. K line items couldn't be matched to inventory and were not decremented."`).
- The `MAX(0, ...)` floor uses Postgres's `GREATEST()` function — both work; pick `GREATEST` for clarity since this is Postgres-only.
- Don't introduce a separate "decrement audit log" table here. The unmatched lines are reported in the import result; matched lines decrement and that's the trail. If audit demand emerges later, build it then.

## Out of scope

- Reverting decrements (no rollback — `docs/order-schema.md#things-to-consider` is explicit). Manual reconciliation if a buggy import goes wrong.
- Non-canceled status handling (`Refunded`, `Returned`, etc.) — only `Canceled` is treated specially. New status strings warrant a follow-up task per the doc's "Things to consider" note.
- Pricing / `inventory.calculated_price` updates triggered by inventory changes (phase 10 / `pricing` concerns).
- Any UI surfacing the unmatched-line warnings (phase 60 toast).
