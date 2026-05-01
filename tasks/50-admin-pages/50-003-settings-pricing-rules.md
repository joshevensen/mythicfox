---
id: "50-003"
title: "Settings — Pricing Rules section (per-product + per-set modals)"
status: pending
phase: "50-admin-pages"
size: L
depends_on: ["50-001", "phase:10-catalog", "phase:30-components"]
references:
  - docs/ux/settings.md#layout
  - docs/ux/settings.md#section-pricing-rules
  - docs/ux/settings.md#data
  - docs/ux/settings.md#states
  - docs/catalog-schema.md
---

## Goal

The first section of the Settings page: per-product pricing rules (base price, high-price threshold, market offset, high offset) with per-set overrides. Each product's rule values are editable via modal; each set's overrides are editable via a second modal that supports null/inherit per-field. Drives the dual-input pricing algorithm in `catalog-schema.md`.

## Acceptance criteria

- [ ] Route `GET /settings` registered under auth middleware, renders an Inertia page using `AdminLayout` from `50-001`. Page title `"Settings"`, subtitle `"Manage pricing rules and review import/export history."`
- [ ] An inline TOC at the top of the page renders pill-link anchors for `#pricing-rules`, `#file-history`, and `#seller-stats`. (File History and Seller Stats are owned by `50-004` and `50-005`; render the anchors so all three pills exist on day one.)
- [ ] The Pricing Rules section (`#pricing-rules`) renders one sub-section per product. Each product sub-section shows:
  - The product name as a header with an Edit (pencil) icon (or full-row clickable area).
  - The four rule values inline beneath the header, formatted via `MfMoney`: `base $X.XX  •  high $X.XX  •  market −$X.XX  •  high −$X.XX`.
  - A "Sets (N)" sub-header where N is the count of `sets` for that product.
  - An alphabetical list of clickable set rows. Sets with any non-null rule field show an `overridden` badge; fully-inheriting sets show no badge.
- [ ] Clicking a product header / Edit icon opens the product rules modal:
  - Title `"{Product} — pricing rules"`.
  - Four `MfMoneyInput` fields: `base_price`, `high_price`, `market_offset`, `high_offset`. All required (products are root — no inheritance).
  - Cancel / Save buttons.
  - On Save: persist to `products`, close modal, refresh the sub-section, toast `"{Product} pricing rules saved."`
- [ ] Clicking a set row opens the set rules modal:
  - Title `"{Set} — pricing rules"`.
  - Subtitle `"Overrides {Product} defaults"`.
  - Four `MfMoneyInput` fields, **nullable** (using the `nullable` prop). Each input shows muted text below it: `"{Product} default: $X.XX"` and a small `↺ inherit` link that clears the field to null.
  - A `"Reset all to product defaults"` button that, after a confirmation, clears all four fields to null.
  - Cancel / Save buttons.
  - On Save: persist non-null fields to `sets` (null fields stay null = inherited), close modal, refresh the row's badge state, toast `"{Set} pricing rules saved."`
- [ ] Server-side validation rejects `base_price > high_price` for both products and sets (effective values after inheritance is applied for sets).
- [ ] First-time-visit empty state when no products exist: `"No products yet — they'll appear after your first PricingCustomExport upload."` with a link to `/catalog`.
- [ ] Mobile (`< 768px`): the Pricing Rules sub-sections stack vertically, the entire product header row is tappable (not just the icon), and the modals become full-screen sheets. The "Inherit from {Product}" muted text sits on its own line beneath each input rather than beside it.
- [ ] Pest feature test `tests/Feature/Admin/Settings/PricingRulesTest.php` covers:
  - Unauthenticated visit redirects to `/login`.
  - Authenticated visit returns 200 and lists every product seeded by the factory, with their inline rule values rendered.
  - POSTing the product rules update endpoint with valid values updates the row and returns 200.
  - POSTing with `base_price > high_price` returns a 422 with the validation error.
  - POSTing the set rules update with all-null fields clears overrides and removes the `overridden` badge on next render.
  - Empty state renders when no products exist.
- [ ] `composer test` passes.

## Implementation notes

- Products and sets are auto-created by the PricingCustomExport / MyPricing imports (phase 10). This page never creates products or sets — only edits their rule fields.
- The "overridden" badge is derived: any of `set.base_price`, `set.high_price`, `set.market_offset`, `set.high_offset` non-null. Compute server-side and pass as a flag on each set row.
- Use `MfMoneyInput` from phase 30 — it handles cents↔dollars conversion at the boundary. Server stores cents; UI displays dollars.
- The TOC pills should anchor to `#pricing-rules` / `#file-history` / `#seller-stats`. Even if `50-004` and `50-005` haven't shipped, render the anchor links — the destinations are stable.
- Reset-all confirmation uses PrimeVue's `useConfirm` per `ux-patterns.md §Forms §Confirmation`. Verb on the destructive button: `"Reset"`, not `"OK"`.
- Per `settings.md §Things to consider`, pricing-rule changes don't recompute `inventory.calculated_price` immediately — that happens on the next pricing-export run. Don't try to recompute here.
- Use Wayfinder for the form action URLs.

## Out of scope

- File History section (that's `50-004`).
- Seller Stats Scraper section (that's `50-005`).
- The PricingCustomExport / MyPricing import flows that auto-create products and sets (phase 10 / phase 60).
- A "Recompute calculated_price for all rows" action (deferred per `settings.md §Things to consider`).
- Archiving stale sets (deferred).
- Account settings (profile name / change password) — handled by the Fortify-provided update routes already in place from `00-002`; no UI work for them in this task.
