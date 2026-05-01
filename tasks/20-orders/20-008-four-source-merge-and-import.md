---
id: "20-008"
title: "Four-source merge + immutable-snapshot order import"
status: pending
phase: "20-orders"
size: L
depends_on: ["20-001", "20-002", "20-003", "20-004", "20-005", "20-006", "20-007"]
references:
  - docs/order-schema.md#import-flow
  - docs/order-schema.md#3-upsert-orders
  - docs/order-schema.md#4-create-order_items-new-orders-only
  - docs/order-schema.md#idempotency
  - docs/saas-design.md#path-convention
---

## Goal

This is the heart of the order side. Take the four parsed source files (OrderList, ShippingExport, PullSheet, PackingSlips PDF), join them in memory by `tcgplayer_order_number`, persist the source files to storage + the `files` table, upsert `orders` (insert new ones with full snapshot, update only mutable fields on existing ones), and create `order_items` for newly-inserted orders only. Inventory decrement is a separate step (`20-009`) but is invoked from this importer.

## Acceptance criteria

- [ ] `App\Services\Orders\OrderImporter` exposes `import(OrderImportInput $input): OrderImportResult`.
  - `OrderImportInput`: paths/uploaded-file objects for each of the four files (OrderList required; the other three optional, per `docs/ux/orders-table.md#modal-layout`).
  - `OrderImportResult`: counts of orders inserted, orders updated, line items created, line items unmatched-to-PDF, line items unmatched-to-inventory, plus the list of `files` rows written.
- [ ] **File persistence (Step 1).** Each provided file is stored via the `spaces` (prod) / `local` (dev) disk under `imports/orders/{YYYY}/{MM}/{ulid}-{slug}.{ext}` per `docs/saas-design.md#path-convention`, and a `files` row is inserted with `type='import'`, `uploaded_at = now()`.
- [ ] **Parse (Step 2).** Each file is parsed via its dedicated parser from `20-004`–`20-007`. Parsing happens **after** persistence so even a parser failure leaves the file on disk for inspection. A parser exception is caught, logged, and added to `OrderImportResult.errors` — the import continues with whichever sources parsed cleanly. OrderList failure is fatal (no orders to import); the other three are partial.
- [ ] **In-memory join.** Build a map keyed by uppercased `tcgplayer_order_number`. For each key: OrderList row (1), optional ShippingExport row (1), optional PullSheet line items (n), optional PDF lines (n).
- [ ] **Step 3 — upsert orders.** For each Order # in OrderList:
  - **New order**: insert a row populating from OrderList + ShippingExport (where present). `order_date` comes from ShippingExport's ISO date when available, else from OrderList's parsed `D, j F Y` date. `imported_at = now()`. ShippingExport-only fields are null when no ShippingExport row exists (e.g. canceled orders).
  - **Existing order**: update **only** `tcgplayer_status`, `tracking_number`, `carrier`. Do not touch totals, addresses, dates, buyer info, or `order_items`. Per `docs/order-schema.md#idempotency`.
- [ ] Orders that appear in ShippingExport, PullSheet, or PDF but **not** in OrderList are skipped — the importer logs a warning per skipped order number into `OrderImportResult.warnings`.
- [ ] **Step 4 — create order_items (new orders only).** For each newly-inserted order: collect the matching PullSheet line items, create one `order_items` row each. Then enrich with PDF prices: for each new `order_items` row, find the matching PDF line by the seven-field key (`order_id`, `product_line`, `set_name`, `product_name`, `number`, `rarity`, `condition`). When a match is found set `unit_price` and `total_price`; when no match, leave both null and increment the unmatched-to-PDF counter.
- [ ] **Decrement hook.** After `order_items` are created, the importer calls into the inventory decrement service from `20-009`. The decrement runs only for newly-created line items, never on existing orders — preserving the immutability/idempotency guarantee.
- [ ] **All persistence happens in a single DB transaction.** If any step throws unexpectedly, the entire batch rolls back. (File-storage writes are best-effort; orphaned objects are tolerable and the cleanup job purges them eventually.)
- [ ] Pest feature tests cover:
  - Happy-path import of all four files for a fresh batch — N orders inserted, N×lines created, line prices populated.
  - Re-import of the same batch is a no-op for orders and order_items (idempotency).
  - Re-import where an order's status changed from `Completed - Paid` to `Canceled` — `tcgplayer_status` updates in place, `order_items` untouched.
  - Re-import where an order had null line prices and the PDF is now provided — `unit_price`/`total_price` **stay null** per the "never refill" rule.
  - Order in ShippingExport but not in OrderList — skipped with a warning.
  - Canceled order with no ShippingExport row — inserted with null shipping fields.
  - OrderList missing from input — fatal, no DB writes.
- [ ] `composer test` passes.

## Implementation notes

- The seven-field PDF match key matches the documentation in `docs/order-schema.md#4-create-order_items-new-orders-only` exactly. TCGPlayer emits identical strings across PullSheet and PDF descriptions; case-sensitive comparison is correct. If real-world data shows drift (extra whitespace, capitalization differences), tighten the comparison to trim+casefold and add a regression test.
- Pull `tcgplayer_order_number` canonicalization (uppercase) into a single helper used by every parser and this importer. The seller-ID validation in `20-010` uses the same canonical form.
- Use `DB::transaction(function () { ... })` and re-throw on failure so the file-storage writes (which happened before the transaction opened) are not rolled back.
- `Order::where('tcgplayer_order_number', $orderNumber)->lockForUpdate()` is appropriate inside the transaction to prevent racing with a concurrent import. In practice the import is queued single-worker, but the lock is cheap insurance.
- Don't optimize the in-memory map size yet — even a 90-day batch is at most a few thousand orders, easily fits in memory.

## Out of scope

- The actual inventory-decrement implementation (`20-009`) — this task **calls** it but does not implement it.
- Seller-ID validation on order numbers (`20-010`) — happens at parse time per row.
- The HTTP controller that fronts this importer (phase 60).
- The queued job wrapper (phase 60 — the orders-table page kicks the job; this task is the synchronous service it calls).
- File-cleanup retention job (phase 70).
- Any UI / toast / progress reporting (phase 60).
