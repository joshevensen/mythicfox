---
id: "20-002"
title: "Create `orders` table and Eloquent model"
status: pending
phase: "20-orders"
size: M
depends_on: ["20-001"]
references:
  - docs/order-schema.md#orders
  - docs/order-schema.md#field-source-map
  - docs/order-schema.md#status
  - docs/ux/orders-table.md#indexes-db
---

## Goal

The `orders` table holds one row per imported TCGPlayer order. It is a denormalized historical snapshot — once a row's immutable fields are written, only `tcgplayer_status`, `tracking_number`, and `carrier` may change on subsequent imports. This task defines the schema and model in isolation so later importer tasks (`20-005`–`20-008`) have a stable target.

## Acceptance criteria

- [ ] Migration `create_orders_table` creates a table with every column in `docs/order-schema.md#orders`:
  - `id` (bigint PK)
  - `tcgplayer_order_number` (string, **unique**)
  - `tcgplayer_status` (string)
  - `buyer_firstname` (string nullable)
  - `buyer_lastname` (string nullable)
  - `buyer_name` (string)
  - `address1`, `address2`, `city`, `state` (2-char), `postal_code`, `country` — all string nullable
  - `order_date` (date)
  - `shipping_method` (string nullable)
  - `item_count` (integer nullable)
  - `product_weight` (decimal, nullable; precision 8, scale 2 — pounds)
  - `product_amount` (integer, cents)
  - `shipping_amount` (integer, cents)
  - `total_amount` (integer, cents)
  - `buyer_paid` (boolean)
  - `tracking_number` (string nullable)
  - `carrier` (string nullable)
  - `imported_at` (timestamp)
  - `created_at`, `updated_at`
- [ ] Indexes per `docs/ux/orders-table.md#indexes-db`:
  - Unique index on `tcgplayer_order_number` (already implied by the unique constraint).
  - Index on `order_date`.
  - Index on `buyer_name`.
  - Index on `tcgplayer_status`.
- [ ] `App\Models\Order` Eloquent model:
  - `$fillable` covers every non-`id` / non-timestamp column.
  - Casts: `order_date` → `date`, `imported_at` → `datetime`, `buyer_paid` → `boolean`, `product_weight` → `decimal:2`, `item_count`/`product_amount`/`shipping_amount`/`total_amount` → `integer`.
  - Has a `items()` HasMany relation to `OrderItem` (the model exists by `20-003` — declare the relation here referring to `\App\Models\OrderItem::class`; this is fine since `20-003` is a hard prerequisite for any importer that loads them).
- [ ] Pest feature test asserting model insert/read round-trip with all fields populated.
- [ ] Pest test asserting the unique constraint on `tcgplayer_order_number` raises an integrity-constraint exception when violated.
- [ ] `composer test` passes.

## Implementation notes

- Generate via `php artisan make:model Order -m`.
- Money is stored as integer cents per `docs/saas-design.md#monetary-values` — do **not** use decimal columns for `product_amount` / `shipping_amount` / `total_amount`.
- `tcgplayer_order_number` should be stored canonically (decision: **uppercase**, matching the format in CSV exports). The lowercase form only appears in the storefront URL; storage uses uppercase. Importers are responsible for canonicalizing on insert (covered in `20-010`).
- Don't add an effective-status accessor here — the orders index page derives its pill from `tcgplayer_status` + `tracking_number`, but that derivation lives in the page's controller/resource (phase 60), not on the model. Keep the model thin.

## Out of scope

- `order_items` table (`20-003`).
- Inventory decrement logic (`20-009`).
- Any importer (`20-004` and later).
- Status enum/lifecycle on the app side — `docs/order-schema.md#status` is explicit that the status string is stored verbatim.
- The orders index page or any controllers (phase 60).
