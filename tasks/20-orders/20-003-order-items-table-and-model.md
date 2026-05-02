---
id: "20-003"
title: "Create `order_items` table and Eloquent model"
status: complete
phase: "20-orders"
size: M
depends_on: ["20-002"]
references:
  - docs/order-schema.md#order_items
  - docs/order-schema.md#field-source-map
  - docs/packingslip-spec.md#card-table
---

## Goal

`order_items` holds the line-item snapshot for each order — the data the packing slip table reads from. It is fully denormalized (no FKs to catalog tables) so that future catalog renames or condition reclassifications never mutate historical orders. This task creates the table and model so the importers and the inventory-decrement task have a target to write into.

## Acceptance criteria

- [x] Migration `create_order_items_table` creates a table with every column in `docs/order-schema.md#order_items`:
  - `id` (bigint PK)
  - `order_id` (bigint FK → `orders`, cascade on delete is acceptable but order rows should never be deleted)
  - `product_line` (string)
  - `set_name` (string)
  - `product_name` (string)
  - `number` (string)
  - `rarity` (string)
  - `condition` (string — full compound TCGPlayer condition)
  - `quantity` (integer)
  - `unit_price` (integer nullable, cents)
  - `total_price` (integer nullable, cents)
  - `tcgplayer_sku_id` (integer nullable)
  - `created_at`, `updated_at`
- [x] Index on `order_id` (Laravel adds this implicitly for the FK on most drivers — verify on Postgres and add explicitly if not).
- [x] Index on `(order_id, product_line, set_name, product_name, number, rarity, condition)` — supports the PDF-line match in `20-008` and the inventory-decrement match in `20-009`. If Postgres complains about index key length, use a partial set of columns (`order_id`, `product_name`, `number`, `condition`) and let the importer scan within that.
- [x] `App\Models\OrderItem` Eloquent model:
  - `$fillable` covers every non-`id`/non-timestamp column.
  - Casts: `quantity`/`unit_price`/`total_price`/`tcgplayer_sku_id` → `integer`.
  - `order()` BelongsTo relation to `Order`.
- [x] Pest feature test inserts an order via the `Order` factory (when available — for now use `Order::create([...])`) plus several `OrderItem` rows and asserts the relationship loads both directions.
- [x] `composer test` passes.

## Implementation notes

- Generate via `php artisan make:model OrderItem -m`.
- Per `docs/order-schema.md#order_items`, **rows are immutable once created** — the application enforces this via the import flow's "do not touch order_items on existing orders" rule (`20-008`), not via DB triggers.
- `unit_price` and `total_price` may be null (PDF wasn't included in the import batch); leave them nullable, do not default to 0.
- Don't model the inventory match key as a separate column — the seven snapshot fields already encode it. Phase 20-009 reads them at decrement time.

## Out of scope

- The factory (`20-012`).
- Any import logic that writes rows (`20-006`–`20-008`).
- Inventory decrement (`20-009`).
- Packing slip rendering (phase 70 — but this table provides the data it reads).
