---
id: "10-004"
title: "Create Inventory model and migration with calculated/override/last-exported price columns"
status: complete
phase: "10-catalog"
size: S
depends_on: ["10-003"]
references:
  - docs/catalog-schema.md#inventory
  - docs/catalog-schema.md#pricing-export
  - docs/ux/inventory.md
---

## Goal

Add `inventory` — the seller's stock count and pricing-state-per-SKU. One row per `card_id` (TCGPlayer SKU). Holds `quantity`, the algorithm's output (`calculated_price`), the seller's manual override (`override_price`), and the last-exported baseline (`last_exported_price`) used by the inventory page's preview modal.

## Acceptance criteria

- [x] Migration `create_inventory_table` matches `docs/catalog-schema.md#inventory`: `id`, `card_id` (FK → cards, **unique** — one inventory row per card), `quantity` (integer, non-negative — enforce via DB check or app-side validation), `calculated_price` (integer cents, nullable), `override_price` (integer cents, nullable), `last_exported_price` (integer cents, nullable), timestamps.
- [x] Unique index on `card_id`.
- [x] `App\Models\Inventory` (or `InventoryItem` — pick one and use consistently) Eloquent model with `$fillable`, integer casts for all cents fields and `quantity`.
- [x] `belongsTo(Card::class)` relation; reciprocal `Card::inventory()` `hasOne` relation.
- [x] Model exposes an **`effective_price`** accessor (`Attribute` or `getEffectivePriceAttribute`) returning `COALESCE(override_price, calculated_price)` (nullable integer cents). This is referenced by the exporter (`10-009`) and the inventory page; centralize it now.
- [x] `InventoryFactory` produces a row attached to a card with a positive default quantity and null pricing fields (matching the bootstrap-import behavior described in the doc).
- [x] Pest tests cover: factory creates a row, `card_id` uniqueness is enforced, `quantity` cannot be negative, `effective_price` returns `override_price` when set, `calculated_price` when override is null, and `null` when both are null.
- [x] `composer test` passes.

## Implementation notes

- Per the doc, "soft remove" from inventory means setting `quantity = 0` and `override_price = null` — there is no soft-delete column. Don't add `SoftDeletes`.
- `last_exported_price` is updated only by the pricing-export Download step (`10-009`). It is never touched manually.
- Acquisition-batch tracking is explicitly out of scope for v1 — re-acquiring a card adds to the existing row's quantity.
- The non-negative constraint on `quantity` can be a DB check constraint (`->check('quantity >= 0')`) — Postgres supports it cleanly. Belt-and-braces with form-request validation later.

## Out of scope

- The recompute logic that fills `calculated_price` — `10-007`.
- The export logic that reads `effective_price` and updates `last_exported_price` — `10-009`.
- Inventory page UI — phase 60.
- Bulk inventory mutations — phase 60.
