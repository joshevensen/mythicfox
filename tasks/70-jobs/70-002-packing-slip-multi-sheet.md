---
id: "70-002"
title: "Packing slip multi-sheet support (Sheet X of N, 20-card chunking)"
status: pending
phase: "70-jobs"
size: M
depends_on: ["70-001"]
references:
  - docs/packingslip-spec.md#capacity--overflow
  - docs/packingslip-spec.md#side-b--packing-slip
  - docs/packingslip-spec.md#things-to-consider
---

## Goal

Orders with more than 20 line items must print as **multiple full slips**, each with its own address panel. Each side B header gets a `Sheet X of N` indicator so the operator can't accidentally pack a multi-sheet order into a single envelope. The split rule is mechanical: 20 cards on slip 1, next 20 on slip 2, and so on. Per [packingslip-spec.md §Capacity / overflow](../../docs/packingslip-spec.md#capacity--overflow), v1 explicitly supports this.

## Acceptance criteria

- [ ] An order with ≤20 line items renders as in `70-001`: a single side-A / side-B pair, **no** `Sheet X of N` indicator (or shows `Sheet 1 of 1` — pick one and stay consistent; spec leaves it to taste).
- [ ] An order with N line items where N > 20 renders `ceil(N / 20)` slip pairs, each emitted as side A then side B with `page-break-after: always`. The full sequence is: A1, B1, A2, B2, ..., Ak, Bk.
- [ ] Each sheet's side B header shows `Sheet X of N` (e.g. `Sheet 2 of 3`). Suggested placement: top-right of the side B content area, above the two-column key/value header. Bold, small (~9pt), per [§Typography](../../docs/packingslip-spec.md#typography).
- [ ] Each sheet's side A address panel is identical (same return + recipient address) — the operator pairs each side-A panel with its corresponding side-B contents, one envelope per sheet.
- [ ] The card table on each sheet shows up to 20 of the order's line items. Items 1–20 on sheet 1, 21–40 on sheet 2, etc. Sort order matches whatever order `70-001` used.
- [ ] The TOTAL row on each sheet shows the **count for that sheet only** (e.g. `TOTAL NUMBER OF CARDS: 20` on a full sheet, `TOTAL NUMBER OF CARDS: 7` on the final partial sheet of a 47-card order). The total is a per-sheet pack-check, not a per-order grand total.
- [ ] Pest feature tests cover:
  - [ ] Order with 20 items: 1 sheet, 1 page pair, all 20 line items rendered.
  - [ ] Order with 21 items: 2 sheets, 2 page pairs (4 pages total), first sheet has 20 line items, second sheet has 1 line item, both side-A panels identical.
  - [ ] Order with 47 items: 3 sheets (20, 20, 7), `Sheet 1 of 3` / `Sheet 2 of 3` / `Sheet 3 of 3` strings present in the rendered HTML in that order.
  - [ ] Order with 1 item: single sheet, no multi-sheet indicator (or `Sheet 1 of 1`, matching the chosen behavior above).
- [ ] `composer test` passes.

## Implementation notes

- Chunking is server-side in the controller: pass an array of `{ sheet_index, sheet_total, line_items }` objects to the Inertia page. The Vue template iterates and emits a side-A / side-B pair per chunk.
- Use Laravel's `collect($order->items)->chunk(20)` for the split; `chunk` preserves order.
- The operator only ever needs the slip for printing — do not over-engineer "first sheet vs continuation sheet" differences. Every sheet is a full slip including the address panel.
- The 20-card max is in the spec, not derived. Hard-code as a class constant (`PackingSlipController::MAX_CARDS_PER_SHEET = 20`) so a future spec change is one-line.

## Out of scope

- Visually different formatting for "continuation" sheets — every sheet is identical except for the line items and the `Sheet X of N` indicator.
- A summary sheet aggregating the order total across sheets — the per-sheet TOTAL serves as the pack-check.
- Reordering line items by name/set — sort order is whatever `70-001` established.
- Optimizing storage or caching of rendered slips — every render is fresh from `orders` + `order_items`.
