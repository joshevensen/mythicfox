---
id: "60-005"
title: "Catalog page — PricingCustomExport upload modal"
status: pending
phase: "61-catalog-pages"
size: M
depends_on:
  - "60-004"
  - "phase:10-catalog"
  - "phase:30-components"
references:
  - docs/ux/catalog.md#upload-flow
  - docs/ux/catalog.md#states
  - docs/catalog-schema.md#pricingcustomexport-import
---

## Goal

Wire the **Upload PricingCustomExport** primary button on the Catalog page to a modal with a single `MfFileDropzone` that posts the CSV, persists it via `files`, and queues the catalog import job. Show queued / running / success / failure feedback. The actual import pipeline (parsing, upserting `products`/`sets`/`cards`, updating `priced_at`) lives in phase 10 — this task is the page-level UI that triggers it.

## Acceptance criteria

- [ ] Clicking **Upload PricingCustomExport** opens a PrimeVue `Dialog` containing one `MfFileDropzone` accepting `.csv` (max 200MB).
- [ ] Client-side validation: extension only (`.csv`). MIME validation happens server-side.
- [ ] Submit posts a multipart request to a controller action (e.g. `POST /catalog/upload`) that:
  - Validates extension + MIME.
  - Persists the file via the `files` table using `App\Support\FilePath` (purpose `pricing`, type `imports`) per `docs/saas-design.md#path-convention`.
  - Dispatches the PricingCustomExport import job from phase 10 with the `files` row ID.
  - Returns an Inertia redirect back to `/catalog` flashing a queued message.
- [ ] On submit success: modal closes, toast displays *"PricingCustomExport queued — refreshing catalog…"*. Upload button enters in-flight state ("Importing…" with spinner badge) until the job reports completion.
- [ ] On job completion: success toast *"Refreshed N cards across {Product Name}"*. Catalog table auto-reloads.
- [ ] On server-side validation failure (wrong header, missing columns): `MfErrorBanner` at top of page with the parse error. The file is still saved to `files` for inspection (asserted in test).
- [ ] Empty-state CTA on `/catalog` ("Your catalog is empty.") opens this same modal — share state via a `useCatalogUploadModal()` composable.
- [ ] Mobile: upload button sticks to the bottom of the viewport on phones for one-thumb reach.
- [ ] Pest feature test: authenticated POST to the upload endpoint with a fake CSV stores a `files` row with `type=import` and dispatches the import job (use `Bus::fake()` and `Storage::fake()`).
- [ ] Pest feature test: posting a non-CSV file (e.g. `.txt`) returns 422.
- [ ] Pest feature test: posting a malformed CSV still creates a `files` row but the response indicates failure (banner content asserted via flash data).
- [ ] `composer test` passes.

## Implementation notes

- Use Inertia's `useForm` for the file submission. Server limit should accept up to 200MB to match the spec.
- Job-completion polling: same pattern as `60-002` — Inertia partial reload polled every ~2s while a `meta.import_in_flight` flag is true on page props.
- Phase 10 owns the actual parser and the `priced_at` bookkeeping. This task hands the persisted file ID to the importer; do not re-implement column mapping here.
- The toast wording with the actual product name is informational — phase 10's job result includes the product name; the controller flashes it to the next response.

## Out of scope

- The PricingCustomExport import pipeline itself (parsing, upserting, `priced_at` update) — phase 10.
- File-history page / browsing past catalog imports (Settings → File history is phase 50).
- Per-set imports beyond what phase 10 supports — the doc explicitly flags per-set `priced_at` as a "consider later" item.
- The catalog table itself (that's `60-004`).
