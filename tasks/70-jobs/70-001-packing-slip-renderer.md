---
id: "70-001"
title: "Render packing slip via HTML + print CSS (browser-printed, no PDF artifact)"
status: complete
phase: "70-jobs"
size: L
depends_on: ["phase:20-orders", "phase:00-foundation"]
references:
  - docs/packingslip-spec.md
  - docs/packingslip-spec.md#paper-envelope-and-printing
  - docs/packingslip-spec.md#side-a--address-panel
  - docs/packingslip-spec.md#side-b--packing-slip
  - docs/packingslip-spec.md#typography
  - docs/packingslip-spec.md#rendering
  - docs/order-schema.md
  - docs/saas-design.md#files
---

## Goal

Generate the Mythic Fox Games custom packing slip from a single order. Per [packingslip-spec.md §Rendering](../../docs/packingslip-spec.md#rendering), this is **HTML + print CSS rendered in the browser** — *not* a server-side PDF, *not* Browsershot. The operator opens the slip page for an order, presses Cmd/Ctrl+P, and prints duplex with long-edge flip. There is no `files` row, no stored artifact: the slip is always re-renderable from `orders` + `order_items`.

This task delivers the single-sheet (≤20 cards) version. Multi-sheet behavior is covered in `70-002`.

## Acceptance criteria

- [x] Route `GET /orders/{order}/packing-slip` resolves to an Inertia page that renders the slip for the given order. Authenticated only (admin session).
- [x] Page emits a **two-page pair**: side A (address panel) followed by side B (slip body), separated by `page-break-after: always` per [§Rendering](../../docs/packingslip-spec.md#rendering).
- [x] `@page { size: letter; margin: 0; }` is applied so absolute-inch positioning matches the spec.
- [x] **Side A** layout matches [§Side A](../../docs/packingslip-spec.md#side-a--address-panel):
  - [x] Top 3.5" and bottom 3.5" of the page are blank.
  - [x] Return address `Mythic Fox Games / 3030 Junction Bay / San Antonio, TX 78109` lives at page rows **4 1/8" – 5 1/8"**, horizontally inset **7/8"** from page left, vertically centered in the 1" band.
  - [x] Recipient address (buyer name + shipping address from the order) lives at page rows **5 3/4" – 7"**, horizontally inset **7/8"**, vertically centered in the 1 1/4" band.
  - [x] Mythic Fox Games card logo on the right side of the middle panel, ~1 1/2" wide, vertically centered within the 4" middle band, right edge ~1" from the page right edge.
  - [x] **No fold guides** on side A.
- [x] **Side B** layout matches [§Side B](../../docs/packingslip-spec.md#side-b--packing-slip):
  - [x] Margins: 1/2" top, 1/2" bottom, 1" left, 1" right (content area 6.5" × 10").
  - [x] Faint hairline fold guides at rows **3.5"** and **7.5"** — two segments per line, each **3/4"** long, extending inward from the left and right page edges; middle of the page stays clear; stroke 0.5pt at ~30% black.
  - [x] Two-column header block at top of content area: left column `ORDER NUMBER` + `ORDER AMOUNT`; right column `BUYER NAME` + `ORDER DATE`. Keys bold, values regular.
  - [x] Card table columns and widths: GAME (0.6"), CARD NAME (2.6"), SET (2.0"), COND. (0.7"), QTY (0.6"). Headers bold. QTY right-aligned. No empty rows.
  - [x] Final row spans GAME + CARD NAME + SET + COND. with `TOTAL NUMBER OF CARDS`, count right-aligned in QTY column. Bold.
  - [x] Footer contact block matches [§Footer](../../docs/packingslip-spec.md#footer) verbatim (3 numbered items, brand email pulled from `config('brand.contact_email')`).
- [x] Typography: system sans-serif stack (Helvetica / Arial / `sans-serif`), body ~10pt, table headers and TOTAL row bold, header keys bold, black on white.
- [x] Long card names truncate with ellipsis to fit the 2.6" CARD NAME column (per [§Things to consider](../../docs/packingslip-spec.md#things-to-consider) — truncation is the safer default).
- [x] **No `files` row** is created when the slip is rendered. Confirmed in a Pest test that asserts `Files::count()` is unchanged across a slip render request.
- [x] Pest feature tests cover:
  - [x] Authenticated GET returns 200 and the response HTML contains the order number, buyer name, formatted order amount (US currency per [saas-design.md §Monetary values](../../docs/saas-design.md#monetary-values)), and order date.
  - [x] Unauthenticated GET redirects to login.
  - [x] An order with 1 line item renders 1 row plus the TOTAL row.
  - [x] An order with 20 line items renders 20 rows plus the TOTAL row, all on a single side B (multi-sheet behavior is `70-002`).
  - [x] Snapshot test (or substring assertions) confirms the address-panel block contains the return address text and the recipient address from the order's `shipping_address_*` fields.
  - [x] No `files` row created by the request.
- [x] `composer test` passes.

## Implementation notes

- **Vue/Inertia page**, server-rendered. Suggested location: `resources/js/pages/Orders/PackingSlip.vue`. Controller: `App\Http\Controllers\PackingSlipController` with a single `show(Order $order)` action.
- The page MUST be styled so it looks correct in print preview at 100% zoom. Screen rendering is a secondary concern — operators only ever print this. Use `@media print` for fold guides if needed; the page can show a "Press Cmd/Ctrl+P to print" banner on screen that hides via `@media print`.
- Use absolute positioning with inch units (`top: 4.125in; left: 0.875in;` etc.) inside a parent `position: relative` page container sized `8.5in × 11in`. Do NOT rely on flexbox/grid for the address-window placement — sub-pixel rounding will misalign with the envelope windows.
- The brand return address is currently hard-coded in the spec. Read it from `config('brand.return_address')` (add this key in this task; default to the spec values) so a future address change doesn't require a code edit.
- Buyer/recipient address fields come from the `orders` table per [order-schema.md](../../docs/order-schema.md). Use the shipping address fields, not the billing fields.
- Currency formatting uses `Illuminate\Support\Number::currency()` per [saas-design.md §Monetary values](../../docs/saas-design.md#monetary-values).
- Date format for ORDER DATE: human-readable US (e.g. `Apr 28, 2026`). Match whatever the existing orders table page uses for consistency.
- Logo: SVG asset in `resources/images/`. Just an `<img>` reference is fine.
- The page belongs in the authenticated admin layout's controller but the rendered slip view should NOT include the admin chrome (sidebar, header). Use a minimal print-only layout.

## Out of scope

- Multi-sheet ("Sheet X of N") behavior — that's `70-002`.
- A "Save as PDF" button in the UI — the operator uses the browser's native print dialog, which has Save-as-PDF built in.
- Persisting the slip to storage — explicitly excluded by the spec.
- Server-side PDF generation via Browsershot — explicitly excluded by [§Rendering](../../docs/packingslip-spec.md#rendering).
- Address validation against USPS or similar — buyer address comes from TCGPlayer as-is.
