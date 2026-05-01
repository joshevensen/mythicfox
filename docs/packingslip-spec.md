# Packing Slip Spec

Authoritative dimensions and layout for the Mythic Fox Games packing slip.

---

## Paper, envelope, and printing

- **Paper**: US Letter, 8.5" × 11", portrait
- **Envelope**: #10 double-window. The dimensions below are sized so the folded packet has ~1/4" of clearance — they override any stock #10 spec
- **Print**: Two-sided, **long-edge flip** (top of side A aligns with top of side B)

### Envelope window measurements

Distances measured from the front of the envelope. The left edge of both windows is **1/2" from the envelope left edge**.

| From | To | Distance |
|---|---|---|
| Envelope top | Upper window top | 5/8" |
| Upper window top | Upper window bottom | 1" |
| Upper window bottom | Lower window top | 5/8" |
| Lower window top | Lower window bottom | 1 1/4" |
| Lower window bottom | Envelope bottom | 3/4" |
| **Envelope total height** | | **4 1/4"** |

---

## Folding

The slip folds into thirds (unequal). The middle 4" panel sits inside the envelope and shows through the windows.

| Region | Page rows (from top) | Fate when folded |
|---|---|---|
| Top panel | 0" – 3.5" | Folds **down** behind the middle |
| Middle panel | 3.5" – 7.5" | Visible — sits in the envelope |
| Bottom panel | 7.5" – 11" | Folds **up** behind the middle |

Final packet: 8.5" × 4". Fits inside the 4 1/4" envelope with 1/4" of vertical slop.

### Fold guides (printed on the slip side only)

Faint hairlines that mark each fold without obstructing the slip body.

- **Position**: at rows **3.5"** and **7.5"** from the page top
- **Shape**: each fold line is split — two segments per line, each **3/4" long**, extending inward from the left and right page edges. The middle of the page stays clear.
- **Stroke**: 0.5pt, ~30% black (faint enough to read through but visible when folding)
- **Side A (address side)**: no fold guides printed; back of the slip stays clean.

---

## Side A — Address panel

Only the **middle 4" band (page rows 3.5" – 7.5")** is visible after folding. Top and bottom 3.5" panels are blank.

Position content within the middle panel so it aligns with the envelope's window cutouts when the packet is seated flush with the envelope top. Addresses are **vertically centered** within their window bands so the ~1/4" of vertical slop doesn't push text out of view.

### Return address (upper window)

| | |
|---|---|
| Window band on page | rows **4 1/8" – 5 1/8"** (3.5" + 5/8", 1" tall) |
| Horizontal text inset | **7/8"** from page left edge (1/2" envelope margin + 3/8" inside the window) |
| Vertical alignment | centered in the 1" band |
| Content | `Mythic Fox Games` / `3030 Junction Bay` / `San Antonio, TX 78109` |

### Recipient address (lower window)

| | |
|---|---|
| Window band on page | rows **5 3/4" – 7"** (3.5" + 5/8" + 1" + 5/8", 1 1/4" tall) |
| Horizontal text inset | **7/8"** from page left edge |
| Vertical alignment | centered in the 1 1/4" band |
| Content | Buyer name + shipping address from the order (typically 3–4 lines) |

### Branding

- Mythic Fox Games card logo on the **right side** of the middle panel.
- Approximately **1 1/2" wide**, vertically centered within the 4" panel.
- Right edge approximately **1" from the page right edge**.

The "Thank you for buying…" line from the previous design is removed.

---

## Side B — Packing slip

| | |
|---|---|
| Margins | **1/2"** top, **1/2"** bottom, **1"** left, **1"** right |
| Content area | 6.5" × 10" |
| Fold guides | edge hairlines at rows 3.5" and 7.5" (see §Folding) |

### Header

Two-column key/value block at the top of the content area. Same fields as the current design.

| Left column | Right column |
|---|---|
| `ORDER NUMBER` *value* | `BUYER NAME` *value* |
| `ORDER AMOUNT` *value* | `ORDER DATE` *value* |

### Card table

Columns and approximate widths (within the 6.5" content area):

| Column | Width | Notes |
|---|---|---|
| GAME | 0.6" | Short code (MtG, Lor, FaB, etc.) |
| CARD NAME | 2.6" | Full card name + collector # |
| SET | 2.0" | Set name |
| COND. | 0.7" | NM / LP / MP / HP, plus foil/finish suffixes (e.g. `NM - RF`) |
| QTY | 0.6" | Right-aligned integer |

- **No empty rows.** The table ends at the last card.
- Final row spans GAME + CARD NAME + SET + COND. with `TOTAL NUMBER OF CARDS`, count right-aligned in QTY.

### Footer

Below the table, a contact block:

```
If you have any questions or concerns about this order:
  1. Email me directly at josh@mythicfoxgames.com
  2. Message me via your TCGplayer order history page
  3. Leave feedback by clicking "Rate Transaction" on your TCGplayer order history page
```

### Capacity / overflow

- **Maximum 20 cards per slip.**
- Orders with more than 20 cards print **multiple full slips** (each with its own address panel). Multiple envelopes will be needed regardless.
- Split rule: 20 cards on slip 1, next 20 on slip 2, etc.

---

## Typography

- Sans-serif throughout (system stack: Helvetica / Arial / `sans-serif` fallback).
- Body text ~10pt; table headers and the `TOTAL NUMBER OF CARDS` row in **bold**.
- Header key labels (`ORDER NUMBER`, `BUYER NAME`, etc.) in bold; their values in regular weight.
- Black on white. No color.

---

## Rendering

- **HTML + print CSS, printed directly from the browser.** No PDF generation, no Browsershot, no headless Chrome. The slip is a server-rendered Inertia page; the user prints it via the browser's native print dialog (Cmd/Ctrl+P).
- Page setup: `@page { size: letter; margin: 0; }`. Position content with absolute coordinates in inches.
- Each slip emits a **two-page pair** (side A then side B), separated by `page-break-after: always`. Multi-slip orders emit additional page pairs in sequence; long-edge duplex pairs each side A with the following side B at the printer.
- No persistent artifact. The slip can be re-rendered at any time from `orders` + `order_items` data — there is no `files` row created for packing slips.
- For the rare case where a PDF copy is wanted (e.g. emailing a customer), the browser's built-in "Save as PDF" handles it on the same page.

---

## Things to consider

- **Print rendering varies by browser.** Chrome's print engine is the reference; Safari and Firefox subtly differ in margin handling and `@page` support. Test the actual fold-line positions on the production browser of choice (Chrome) before relying on the millimeter-level alignment.
- **Printer margins can shift the page.** Even with `@page { margin: 0 }`, some printers add a non-printable border (typically 0.1–0.2") that effectively shifts the address window's apparent position relative to the envelope. Test with the actual printer + envelope combo before printing real customer orders.
- **Duplex orientation isn't programmatic.** The browser print dialog can't enforce long-edge flip; the operator has to pick it manually. A printed slip with the wrong duplex setting puts addresses on the wrong panel. Consider a one-time post-it on the printer, or print a test slip after every printer config change.
- **Long card names overflow the table.** The card-list column in the slip body has a fixed width. Cards with very long names (some Lorcana/F&B cards push 40+ characters) will either truncate or wrap. Decide which behavior is acceptable and style accordingly; truncation with an ellipsis is the safer default.
- **Multi-slip orders need multi-envelope packing.** When an order has more than 20 cards, multiple full slips print. The operator needs to remember to pack into multiple envelopes. The slip itself doesn't visually flag "1 of 3" — consider adding a "Sheet X of N" header on multi-slip orders so the operator can't accidentally pack them all into one envelope.
- **Print preview lies.** Browsers occasionally render print preview slightly differently from the actual paper output. Always validate on the physical printer before assuming the spec is correct.
- **Address window slop.** The 1/4" vertical clearance accommodates packet position variance inside the envelope. If new envelope stock has different inner dimensions than the current spec, the addresses may peek outside the windows. Verify with each new envelope batch.
