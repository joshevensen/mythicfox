---
id: "20-011"
title: "Re-import and replay handling (idempotency hardening)"
status: complete
phase: "20-orders"
size: M
depends_on: ["20-008", "20-009"]
references:
  - docs/order-schema.md#idempotency
  - docs/order-schema.md#things-to-consider
  - docs/saas-design.md#path-convention
---

## Goal

`20-008` already implements the documented idempotency rules. This task hardens them with **explicit regression tests** for every re-import scenario, formalizes the "files row per upload" audit behavior (re-uploading the same file produces a **new** `files` row), and adds the result-summary plumbing so the import surface in phase 60 can show `"Imported N orders (M new, K updated)."`.

The intent is to lock the contract: any future change to `OrderImporter` must keep these tests green.

## Acceptance criteria

- [x] Every row in `docs/order-schema.md#idempotency` has a matching Pest feature test:
  - Same batch uploaded twice → no new `orders` rows, no new `order_items` rows. `OrderImportResult.ordersInserted == 0`, `ordersUpdated == 0`.
  - Status changes upstream (e.g. `Completed - Paid` → `Canceled`) → `tcgplayer_status` updates in place; `order_items` untouched; `ordersUpdated == 1`.
  - Tracking added after first import → `tracking_number` and `carrier` update; nothing else changes.
  - Order in OrderList but not in earlier import (newer order) → inserted fresh, line items created, decrement runs.
  - Order had null line prices, later batch includes the PDF → **line prices stay null** ("never refill" rule).
- [x] **Files audit**: re-uploading the same physical file produces a fresh `files` row with a fresh ULID-based path. The original row stays. (Per `docs/saas-design.md#files`, the table is an audit log; deduplication is not desired.) Test asserts two rows exist after two uploads of the same file.
- [x] **Decrement is not re-applied**: re-importing a batch where every order is pre-existing causes **zero** inventory changes. Test sets up a card with quantity `10`, imports an order for `qty 3` (inventory now `7`), re-imports the same batch (inventory stays `7`).
- [x] **Status-flip-to-Canceled does not undo decrement**: if an order is imported as `Completed - Paid` (decrement runs) and re-imported as `Canceled`, the inventory **stays decremented**. The cancellation check in `20-009` is "skip decrement on canceled orders during the **initial** import" — it is not a refund. Document this in the test name and assert against actual inventory.
- [x] `OrderImportResult` (defined in `20-008`) gains a clean `summaryLine(): string` method that emits `"Imported {N} orders ({M} new, {K} updated)."` plus, when applicable, ` "L line items couldn't be matched to inventory and were not decremented."` — matching the toast string in `docs/order-schema.md#5-decrement-inventory-new-orders-only-non-cancelled`.
- [x] Pest unit test for `summaryLine()` covers all permutations: only-new, only-updated, mixed, with and without unmatched lines.
- [x] `composer test` passes.

## Implementation notes

- Most of these scenarios are testable against the existing `OrderImporter` from `20-008` — this task is primarily a **test pass** plus a small `summaryLine()` addition. If a test reveals a behavior gap, fix it in `OrderImporter` and note the fix in this task's commit.
- Use `RefreshDatabase` and the order/catalog factories (`20-012`, plus phase 10 factories). Build minimal fixtures inline for parser inputs — temp CSV files written to `storage/framework/testing/`.
- The "status-flip-to-Canceled does not undo decrement" behavior is **intentional and documented** in `docs/order-schema.md#things-to-consider` ("Bulk re-imports never undo decrements"). The test exists to prevent a well-meaning future change from "fixing" this.
- If a regression test reveals that re-importing actually does refill `unit_price` (because `20-008` accidentally took the existing-order path through line-item creation), revert the offending logic — the doc is explicit that the rule trades convenience for snapshot immutability.

## Out of scope

- Manual reconciliation tooling for "decrement gone wrong" (out of v1 — `docs/order-schema.md#things-to-consider` notes this is acceptable for now).
- Changing the never-refill rule. If the operator wants line-price backfills, that's a separate product decision and a separate task.
- Any UI for retry / re-upload (phase 60).
