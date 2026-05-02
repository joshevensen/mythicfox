---
id: "10-002"
title: "Create Set model and migration with nullable per-set pricing overrides"
status: complete
phase: "10-catalog"
size: S
depends_on: ["10-001"]
references:
  - docs/catalog-schema.md#sets
  - docs/catalog-schema.md#pricing-logic
---

## Goal

Add the `sets` table — a TCGPlayer "Set Name" inside a product. Sets carry the same four pricing-rule fields as products, but every column is nullable: a null on a set means "use the product's value." This task creates the table, model, and the relation back to `Product`.

## Acceptance criteria

- [x] Migration `create_sets_table` matches `docs/catalog-schema.md#sets`: `id`, `product_id` (FK → products, cascade or restrict per Laravel default — restrict), `name` (string), `base_price` / `high_price` / `market_offset` / `high_offset` (all integer cents, **nullable**), timestamps.
- [x] Composite unique index on `(product_id, name)`.
- [x] `App\Models\Set` (or `CardSet` if `Set` collides with PHP reserved naming — pick one and document the choice in the migration comment) Eloquent model exists with `$fillable`, integer casts for the four cents fields.
- [x] `belongsTo(Product::class)` relation defined; `Product::sets()` resolves cleanly.
- [x] `SetFactory` produces a row tied to a product, with all four override fields null by default and an optional state for setting them.
- [x] Pest tests cover: factory creates a row attached to a product, the unique constraint on `(product_id, name)` is enforced, all four override fields default to null.
- [x] `composer test` passes.

## Implementation notes

- The doc uses `sets` as the table name; PHP `Set` is not actually a reserved class, but check Laravel's collision list. If naming the model `Set` causes any IDE/static-analysis grief, name it `CardSet` and keep `sets` as the table — set `$table = 'sets'` explicitly.
- Pricing-rule resolution (set falls through to product when null) lives in the pricing algorithm task (`10-007`), not here. This task only models the data.
- The PricingCustomExport importer (`10-005`) upserts on `(product_id, name)` and never touches the rule fields after insert — same pattern as products.

## Out of scope

- Per-set rule editing UI — phase 60 (catalog page).
- The recompute trigger when set rules change — that's `10-008`.
- Seeding real set rows — `10-010`.
