---
id: "20-012"
title: "Factories and seeders for the orders domain"
status: pending
phase: "20-orders"
size: S
depends_on: ["20-002", "20-003", "20-001"]
references:
  - docs/order-schema.md#orders
  - docs/order-schema.md#order_items
  - docs/saas-design.md#files
---

## Goal

Tests across phase 20 (and downstream phases 60/70) need realistic `Order`, `OrderItem`, and `File` rows on demand. Factories provide that. A small seeder populates a development database with a representative slice for manual UI work. This is the last task in the phase — once it ships, phase 30 (components) can begin.

## Acceptance criteria

- [ ] `database/factories/OrderFactory.php` produces a fully-populated `orders` row:
  - `tcgplayer_order_number`: a generated hex string starting with the configured `TCGPLAYER_SELLER_ID` (fallback `623394E9` if env empty), followed by two more `-` separated segments. Uppercase.
  - `tcgplayer_status`: `'Completed - Paid'` by default.
  - `buyer_firstname`/`buyer_lastname`/`buyer_name` (combined): from `fake()->name()`.
  - Address fields: realistic US address from faker.
  - `order_date`: random within last 90 days.
  - `shipping_method`: `'Standard (7-10 days)'`.
  - `item_count`: 1–10.
  - `product_weight`: 0.10–2.00 lbs.
  - `product_amount`/`shipping_amount`/`total_amount`: integer cents, with `total_amount = product_amount + shipping_amount`.
  - `buyer_paid`: `true`.
  - `tracking_number`/`carrier`: null by default.
  - `imported_at`: `now()`.
- [ ] Factory states:
  - `canceled()` — sets `tcgplayer_status = 'Canceled'`, nulls all ShippingExport-only fields (`address1`–`country`, `tracking_number`, `carrier`, `item_count`, `product_weight`, `shipping_method`).
  - `shipped()` — sets `tracking_number` (faker tracking-number style) and `carrier = 'USPS'`.
  - `withoutLinePrices()` — convenience for the "PDF wasn't uploaded" scenario; combined with `OrderItemFactory::withoutPrice()`.
- [ ] `database/factories/OrderItemFactory.php` produces line items consistent with the documented snapshot fields:
  - `product_line`: cycles through `Magic`, `Lorcana`, `Flesh and Blood`.
  - `set_name`, `product_name`, `number`, `rarity`: faker words / numbers — these are denormalized strings, no need to match a real catalog row.
  - `condition`: cycles through `Near Mint`, `Lightly Played`, `Near Mint Foil`, etc. (use the documented compound vocabulary in `docs/catalog-schema.md#condition-vocabulary`).
  - `quantity`: 1–4.
  - `unit_price`: integer cents, 25–5000.
  - `total_price = unit_price * quantity`.
  - `tcgplayer_sku_id`: faker integer.
- [ ] `OrderItemFactory::withoutPrice()` state nulls both `unit_price` and `total_price`.
- [ ] `OrderItemFactory::forCard($card)` state takes a phase-10 `Card` model and copies its catalog fields into the snapshot fields, so feature tests for inventory decrement can wire an order item to a real catalog row without redundant string typing.
- [ ] `database/factories/FileFactory.php` produces a `files` row with `type='import'`, a path matching the documented convention, and `uploaded_at = now()`.
- [ ] `database/seeders/OrderSeeder.php` (or a section of `DatabaseSeeder`) creates ~25 orders across the last 90 days, mostly `Completed - Paid`, a few `Canceled`, a few `shipped()`. Each order gets 1–4 line items. **No inventory decrement** runs from the seeder — it's data-only.
- [ ] The `Order::factory()->canceled()->create()` produces a row that round-trips through the model casts cleanly (canceled-with-null-shipping-fields scenario from `20-008`).
- [ ] `composer test` passes.

## Implementation notes

- Generate factories via `php artisan make:factory OrderFactory --model=Order` etc.
- Don't add a separate seeder for `order_items` — tie creation to the order via factory `has()` / `afterCreating()` so calling code can do `Order::factory()->has(OrderItem::factory()->count(3))->create()`.
- Money values stay in cents. Don't accidentally store dollars.
- The `forCard()` state requires the phase-10 `Card` factory to exist. If it doesn't yet, gate the state behind a `class_exists` check or move it into a follow-up — but `phase:10-catalog` is a hard prerequisite for `20-009` so by the time anyone needs `forCard()` in tests, phase 10 is done.
- The seeder is **not** a CI fixture — it's for manual dev data. Don't run it in tests; tests use factories directly.

## Out of scope

- Catalog-side factories (phase 10).
- A "bulk import a directory of fixture CSVs" seeder — overkill for v1; if needed later, build it as a separate Artisan command.
- Performance-test data (millions of orders) — the seeder targets development realism, not load testing.
