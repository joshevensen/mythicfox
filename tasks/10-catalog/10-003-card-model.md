---
id: "10-003"
title: "Create Card model and migration keyed on tcgplayer_id"
status: pending
phase: "10-catalog"
size: M
depends_on: ["10-002"]
references:
  - docs/catalog-schema.md#cards
  - docs/catalog-schema.md#condition-vocabulary
  - docs/catalog-schema.md#column--field-map-pricingcustomexport--mypricing-share-this
---

## Goal

Stand up `cards` — the per-SKU table where **each row is one (product, condition) pair** as TCGPlayer models them. `tcgplayer_id` is the upsert key for every catalog import; it's the only stable identity that survives renames and condition splits. This is the table later joined against by inventory, pricing, and order line items.

## Acceptance criteria

- [ ] Migration `create_cards_table` matches `docs/catalog-schema.md#cards`: `id`, `set_id` (FK → sets), `tcgplayer_id` (integer, **unique**), `product_name` (string), `number` (string), `rarity` (string), `condition` (string — verbatim TCGPlayer compound string), `market_price` (integer cents, nullable), `low_price` (integer cents, nullable), timestamps.
- [ ] `tcgplayer_id` has a unique index — it's the upsert key.
- [ ] Add a covering index on `(set_id, product_name, number)` per the "Things to consider" note in the doc — the catalog page's heaviest aggregation hits this combination.
- [ ] `App\Models\Card` Eloquent model with `$fillable`, integer casts for `tcgplayer_id`, `market_price`, `low_price`.
- [ ] `belongsTo(CardSet::class)` (or whatever name `10-002` chose) relation defined; reciprocal `Set::cards()` `hasMany` relation defined.
- [ ] `condition` is stored verbatim — no enum, no normalization. Don't add a check constraint; the 11 strings in the doc are observed, not exhaustive (TCGPlayer may emit new ones for new games).
- [ ] `CardFactory` produces a row attached to a set, with realistic default values (e.g. random NM condition, random rarity, sensible cents prices).
- [ ] Pest tests cover: factory creates a row, `tcgplayer_id` uniqueness is enforced, both `market_price` and `low_price` accept null, condition strings round-trip verbatim (case + spaces preserved).
- [ ] `composer test` passes.

## Implementation notes

- The schema deliberately moves `condition` from `inventory` to `cards`. Each `cards` row is a SKU; `inventory.condition` would have been redundant.
- Decimal-to-cents parsing for `market_price` / `low_price` happens in the importer (`10-005`), not here. The model just stores whatever cents value is handed to it.
- `rarity` strings are game-specific (Magic uses single letters, Lorcana / Flesh & Blood use words). Do not normalize. The doc explicitly forbids it.
- The catalog page's aggregation `(set_id, product_name, number)` will be the hottest read path — get the index right now so we don't pay for it later.

## Out of scope

- Inventory rows — `10-004`.
- Importer logic — `10-005` and `10-006`.
- Card-grouping or materialized views for the catalog page — phase 60 if performance demands it.
- Rename audit trails (the doc flags this as an open question; defer until the operator hits a real rename).
