---
id: "20-010"
title: "Validate `tcgplayer_order_number` against `TCGPLAYER_SELLER_ID`"
status: pending
phase: "20-orders"
size: S
depends_on: ["20-004", "20-005", "20-006", "20-007"]
references:
  - docs/order-schema.md#orders
  - docs/order-schema.md#things-to-consider
---

## Goal

Every TCGPlayer order number is a hyphen-separated hex string whose **first segment is the seller ID**. Mythic Fox Games' seller ID is `623394e9` (case-insensitive) and is configured in `.env` as `TCGPLAYER_SELLER_ID` (per `00-004`). Validating that imported order numbers start with the configured seller ID is a cheap sanity check against accidentally importing someone else's exports — exactly the safety rail called out in `docs/order-schema.md#orders`.

## Acceptance criteria

- [ ] A reusable validator (e.g. `App\Services\Orders\SellerIdValidator`) exposes `isValid(string $orderNumber): bool` and `assertValid(string $orderNumber): void` (the latter throws `App\Exceptions\OrderImport\WrongSellerException` carrying the offending order number and the configured seller ID).
- [ ] The validator reads the configured ID via `config('services.tcgplayer.seller_id')` (already wired by `00-004`).
- [ ] Comparison is **case-insensitive** — `623394E9-…` and `623394e9-…` both match a seller ID configured as `623394e9`.
- [ ] The validator splits the order number on `-` and compares only the first segment. Trailing segments (`23CAFE-565FC`, etc.) are not consulted.
- [ ] All four parsers (`20-004`–`20-007`) call `assertValid()` on every emitted order number before returning the row. A bad order number aborts that file's parse with the domain exception; the merge step in `20-008` catches it, adds it to `OrderImportResult.errors`, and skips the file (matching the partial-failure behavior already specified there).
- [ ] If `TCGPLAYER_SELLER_ID` is empty/null, the validator's `isValid()` returns `true` (skips the check). This avoids breaking the test suite when the config isn't set, and lets the operator opt out by leaving the env empty. Document this fall-through in the class docblock.
- [ ] Pest unit tests:
  - Configured `623394e9`; `623394E9-…-...` and `623394e9-…-...` both validate.
  - Configured `623394e9`; `ABCD1234-…-...` raises `WrongSellerException`.
  - Configured `''`; any order number passes.
  - Empty / single-segment order number raises `WrongSellerException`.
- [ ] Pest feature test: an OrderList containing an alien seller's order number → import surfaces the exception in the result's `errors` collection without persisting any orders from that file.
- [ ] `composer test` passes.

## Implementation notes

- The seller ID is sometimes called the "seller short ID" — it's a hex string, not a UUID. Keep the comparison purely lexicographic with `strcasecmp()` or `Str::lower()` — don't try to validate the format itself (length, hex chars).
- Place the validator under `App\Services\Orders\` so it's adjacent to the parsers that use it.
- The fall-through-when-empty rule is intentional: the test suite uses a `.env.example`-derived `.env.testing` where `TCGPLAYER_SELLER_ID` may be unset. Production has the value set; CI sets it via the workflow secrets / `.env.example` placeholder.

## Out of scope

- Validating the format of the trailing segments — TCGPlayer can change the format.
- Per-row UI feedback during import (phase 60 toast handles aggregate errors).
- Multi-seller support — this app is explicitly single-tenant for one seller.
