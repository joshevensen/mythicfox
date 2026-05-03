---
id: "60-003"
title: "Orders page — packing-slip + TCGPlayer actions (per-row + bulk)"
status: complete
phase: "60-orders-pages"
size: M
depends_on:
  - "60-001"
  - "phase:20-orders"
references:
  - docs/ux/orders-table.md#per-row-actions
  - docs/ux/orders-table.md#bulk-action-print-packing-slips
  - docs/packingslip-spec.md
---

## Goal

Wire the action icons in the Orders table — both per-row ("Print packing slip", "Open in TCGPlayer") and the bulk top-of-table ("Print packing slips" for selected rows). The actions open new tabs to dedicated print routes that auto-trigger the browser print dialog. **The actual packing-slip rendering / PDF generation lives in phase 70** — this task only wires the UI buttons to the route URLs and leaves the routes themselves as stubs that phase 70 fills in.

## Acceptance criteria

- [x] Per-row 🖨️ printer icon (PrimeIcon `pi pi-printer`) at the right edge of each order row. Tooltip: "Print packing slip". On click, opens new tab to `/orders/{tcgplayer_order_number}/packing-slip` (`target="_blank" rel="noopener"`).
- [x] Per-row 🔗 external icon (`pi pi-external-link`). Tooltip: "Open in TCGPlayer". Opens new tab to `https://sellerportal.tcgplayer.com/orders/{tcgplayer_order_number}` (`target="_blank" rel="noopener"`).
- [x] Both icons have ≥44×44px touch targets per `docs/ux/ux-patterns.md#responsive-behavior`.
- [x] Bulk action bar (visible when ≥1 row selected) shows a single primary button: **Print packing slips**. On click, opens new tab to `/orders/print?ids={comma-separated tcgplayer_order_numbers}`.
- [x] Bulk print confirms-to-proceed when ≥25 orders are selected, per `docs/ux/orders-table.md#things-to-consider` ("Bulk print can produce huge documents"). Use `MfConfirmDialog` with copy along the lines of "Print N packing slips? Large batches can be hard to recover if printing is interrupted." Verbs: Cancel / Print.
- [x] "Select all N matching" link in the action bar selects across pages (server-side selection of matching IDs given current filters), not just the current visible page.
- [x] Routes `GET /orders/{tcgplayer_order_number}/packing-slip` and `GET /orders/print` are registered behind auth middleware. Both return a stub Inertia page (or simple Blade) noting "Packing slip rendering is implemented in phase 70" — they exist so the icons aren't 404, but the actual render is phase 70's job.
- [x] Stub routes load the relevant order(s) by `tcgplayer_order_number` to confirm authorization and 404 on unknown order numbers — this validation contract belongs here so phase 70 inherits it.
- [x] Mobile card-row layout exposes both action buttons at the bottom of each card per `docs/ux/orders-table.md#mobile-layout`.
- [x] Pest feature test: per-row print URL is rendered with the correct order number for each row in the page response.
- [x] Pest feature test: GET on the per-order print stub route with a valid order returns 200; with an unknown order returns 404.
- [x] Pest feature test: GET on `/orders/print?ids=...` with valid IDs returns 200; with a missing ID returns 404.
- [x] Pest feature test: bulk action bar renders only when selection is non-empty (asserts via Inertia prop or component test).
- [x] `composer test` passes.

## Implementation notes

- The new tab + auto-print pattern: phase 70 will add `window.print()` on load inside the rendered page. This task only points the icons at the URLs; the render is phase 70.
- Use Wayfinder typed routes for both stub URLs so phase 70 can change templates without touching call sites. The TCGPlayer external URL is a hardcoded base + interpolation (not a Wayfinder concern).
- "Select all N matching" implementation: when the master checkbox is toggled and matching orders span multiple pages, store the selection as a filter signature (current filter query string) rather than enumerating IDs. The bulk-print URL builder then resolves the filter signature to a comma-separated `ids=` server-side. This avoids hitting URL length limits for large selections — but **cap bulk print at 100 orders** with an error toast if exceeded, matching the "huge documents" concern.
- The stub route implementations should be intentionally minimal — a controller action that loads the order(s), returns a placeholder view that explicitly says "Phase 70 renders here." Phase 70 swaps the view body without touching the controller action signature.
- `pi pi-printer` and `pi pi-external-link` are the canonical icons per `docs/ux/ux-patterns.md#stack`.

## Out of scope

- **PDF rendering / Browsershot integration** — that is `phase:70-jobs`. This task does not depend on phase 70; it depends only on phase 20 for order data and references phase 70 only by pointing at routes phase 70 will fill in.
- Tracking whether a slip was printed — explicitly not stored per `docs/ux/orders-table.md#per-row-actions` ("printing is a presentation concern, not state").
- Quarterly verification of the TCGPlayer URL pattern — operational concern, not a code task.
- The Import flow (that's `60-002`).
- The Orders table itself (that's `60-001`).
