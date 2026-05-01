---
id: "00-005"
title: "Install Browsershot and smalot/pdfparser composer packages"
status: pending
phase: "00-foundation"
size: S
depends_on: []
references:
  - docs/saas-design.md#stack--deployment
  - docs/saas-design.md#seller-stats-scraper
  - docs/order-schema.md#packing-slip-pdf-source
---

## Goal

Two server-side libraries are needed by later phases: Browsershot for headless-Chrome rendering of the TCGPlayer seller page (phase 70 scraper), and `smalot/pdfparser` for extracting line-item text from TCGPlayer-issued packing-slip PDFs during order import (phase 20). Add both to `composer.json` now so dependency resolution and CI happen once, not piecemeal.

## Acceptance criteria

- [ ] `composer require spatie/browsershot` succeeds and the package appears in `composer.json` `require` (not `require-dev`).
- [ ] `composer require smalot/pdfparser` succeeds and appears in `composer.json` `require`.
- [ ] A trivial Pest unit test exists for each:
  - `BrowsershotInstalledTest` — instantiates `Spatie\Browsershot\Browsershot::url('https://example.com')` and asserts no exception (does NOT actually render — just confirms the class autoloads).
  - `PdfParserInstalledTest` — instantiates `Smalot\PdfParser\Parser` and asserts no exception.
- [ ] Both tests are tagged `@group dependencies` (or pest equivalent) so they can be skipped in CI environments where Chrome isn't installed if needed later.
- [ ] `composer test` passes.

## Implementation notes

- Browsershot's actual `bodyHtml()` / `screenshot()` calls require Chrome on the host. We are NOT testing rendering here — just that the package autoloads. Real render tests live alongside the scraper job in phase 70.
- `smalot/pdfparser` is pure PHP; no system dependencies.
- If a newer Browsershot version requires `spatie/temporary-directory` or other peers, accept the transitive install — Composer handles it.

## Out of scope

- Installing Chrome on the production droplet (that's a phase 80 operator-manual task).
- Writing the actual scraper or PDF parser (phase 70 / phase 20).
