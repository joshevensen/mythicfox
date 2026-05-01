---
id: "10-001"
title: "Create Product model and migration with default pricing rules"
status: pending
phase: "10-catalog"
size: S
depends_on: ["phase:00-foundation"]
references:
  - docs/catalog-schema.md#products
  - docs/catalog-schema.md#pricing-logic
  - docs/catalog-schema.md#default-product-values
---

## Goal

Stand up the `products` table — the top of the catalog hierarchy. A product is a TCGPlayer "Product Line" (`Magic`, `Lorcana TCG`, `Flesh & Blood TCG`) and owns the default pricing rules that apply to every card in the game unless overridden at the set level. Every later catalog table hangs off this one, so it ships first.

## Acceptance criteria

- [ ] Migration `create_products_table` matches the schema in `docs/catalog-schema.md#products` exactly: `id`, `name` (string, unique), `base_price` (integer cents, default 25), `high_price` (integer cents, default 1000), `market_offset` (integer cents, default 0), `high_offset` (integer cents, default 15), `priced_at` (timestamp nullable), timestamps.
- [ ] `name` has a unique index — products are upserted on `name`.
- [ ] `App\Models\Product` Eloquent model exists with `$fillable` covering every writable column.
- [ ] All four pricing-rule fields and `priced_at` are cast to appropriate types (integer for cents, datetime for `priced_at`).
- [ ] Model exposes a `cards` relation (placeholder `hasManyThrough` via `sets` is fine — wire fully in `10-003`) and a `sets` relation (`hasMany`) — even though `sets` doesn't exist yet, define the relation method; it'll resolve once `10-002` lands.
- [ ] `database/factories/ProductFactory.php` produces a valid product with the default pricing values from the doc.
- [ ] Pest unit test covers: factory creates a row, default pricing values match the doc, `name` uniqueness is enforced.
- [ ] `composer test` passes.

## Implementation notes

- All money is stored as integer cents per `docs/saas-design.md` — never floats, never decimals.
- `priced_at` is bumped by the PricingCustomExport importer (`10-005`), not by anything in this task. Just declare it nullable.
- Don't seed actual product rows here (Magic / Lorcana / Flesh & Blood) — that lives in `10-010`.
- Do **not** touch `name` on subsequent upserts (per the doc's import rules) — this is an importer concern; the model doesn't need to enforce it. Just note it for `10-005`.

## Out of scope

- `sets`, `cards`, `inventory` tables — those are `10-002` through `10-004`.
- Pricing algorithm code — `10-007`.
- Admin UI for editing pricing rules — phase 50/60.
- Product seed data — `10-010`.
