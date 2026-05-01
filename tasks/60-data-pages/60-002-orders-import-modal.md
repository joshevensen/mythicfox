---
id: "60-002"
title: "Orders page — Import modal (four-file dropzone, queues importer)"
status: pending
phase: "60-data-pages"
size: M
depends_on:
  - "60-001"
  - "phase:20-orders"
  - "phase:30-components"
references:
  - docs/ux/orders-table.md#import-flow
  - docs/ux/orders-table.md#states
  - docs/order-schema.md#import-flow
  - docs/order-schema.md#source-files
---

## Goal

Wire the **Import orders** primary button on the Orders page to a PrimeVue Dialog modal with four labeled file slots (OrderList, ShippingExport, PullSheet, PackingSlips). On submit, files are uploaded, persisted via the `files` table, and the order import job is queued. UI feedback covers the queued / running / success / partial-failure states. The actual parsing and persistence pipeline already lives in phase 20 — this task is the page-level UI that triggers it.

## Acceptance criteria

- [ ] Clicking the **Import orders** header button opens a PrimeVue `Dialog` containing four `MfFileDropzone` slots labeled per `docs/ux/orders-table.md#modal-layout`: OrderList (required, `.csv`), ShippingExport (optional, `.csv`), PullSheet (optional, `.csv`), PackingSlips (optional, `.pdf`).
- [ ] Each slot accepts a single file; selecting a file replaces the slot label with the filename and shows a small × to clear it.
- [ ] Each optional slot displays the trade-off hint described in `docs/ux/orders-table.md#modal-layout` (e.g. "Without ShippingExport, addresses/tracking are null").
- [ ] **Import** submit button disabled until OrderList is provided.
- [ ] Submit posts a multipart request to a controller action (e.g. `POST /orders/import`) that:
  - Validates file MIME / extension (`.csv` for first three, `.pdf` for the fourth).
  - Persists each provided file via the `files` table using `App\Support\FilePath` per `docs/saas-design.md#path-convention` (purpose `orders`, type `imports`).
  - Dispatches the order import job from phase 20 with the persisted file IDs.
  - Returns an Inertia redirect back to `/orders` flashing a queued message.
- [ ] On submit success: modal closes, toast displays *"Import queued — processing N orders…"*, the Import button enters the in-flight state ("Importing…" with spinner badge) until the job reports completion.
- [ ] On job completion (Inertia partial reload or polling — see notes): success toast *"Imported N orders (M new, K updated)."*, table auto-reloads.
- [ ] On server-side validation failure: `MfErrorBanner` at top of page lists which file(s) failed and why; files that did parse continue to be processed. Failed files are still saved to `files` for inspection (asserted in test).
- [ ] Dashboard quick-action shortcut works: visiting `/orders?import=1` opens the import modal on mount (per `docs/ux/ux-patterns.md#url-driven-state`).
- [ ] Pest feature test: authenticated POST to the import endpoint with a fake OrderList CSV stores a `files` row with `type=import` and dispatches the importer job (use `Bus::fake()` and `Storage::fake()`).
- [ ] Pest feature test: posting without OrderList returns a 422 / validation error (form validation rejects).
- [ ] Pest feature test: posting with a malformed CSV still creates a `files` row but flashes the partial-failure banner content.
- [ ] `composer test` passes.

## Implementation notes

- `MfFileDropzone` from phase 30 already handles drop-or-browse and single-file constraints; the page only composes four of them in the dialog body.
- For job-completion feedback: simplest approach is Inertia's `router.reload({ only: ['orders'] })` polled on a short interval (e.g. every 2s) while a `meta.import_in_flight` flag is true on the page props, set by checking for any unfinished `files` rows linked to a queued import. Keep the polling internal to a composable; don't sprinkle setInterval calls.
- The phase 20 importer is responsible for the actual ingest contract; this task just hands it the persisted `files` row IDs and trusts the rest. Don't duplicate validation logic here beyond MIME/ext.
- Form upload: use Inertia's `useForm` with file fields. Server limits should match `docs/saas-design.md` (orders modal accepts up to ~50MB total; a 200MB cap on a single file is fine).
- The empty-state CTA from `60-001` ("No orders yet — import your first batch") opens this same modal — use a shared `useImportModal()` composable so both entry points share state.
- The bottom-stuck Import button on phones (mobile layout) should use the same modal trigger.

## Out of scope

- The order import pipeline itself (parsing CSVs/PDFs, upserting orders, decrementing inventory) — phase 20.
- Files-page UI for browsing past imports (Settings → File history is phase 50).
- Handling the case where the user uploads the wrong four files together (we trust phase 20's validation; this task only flashes whatever the importer reports).
- Real-time progress percentage during a long import — a simple "queued / running / done" toggle is sufficient.
