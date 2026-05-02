---
id: "30-013"
title: "Build MfFileDropzone (drag-drop + click-to-browse uploader)"
status: complete
phase: "30-components"
size: M
depends_on: ["30-002"]
references:
  - docs/ux/components.md#mffiledropzone
  - docs/order-schema.md
  - docs/saas-design.md
---

## Goal

CSV / PDF imports happen on the Orders page (four-file batch upload), the Inventory page (PricingCustomExport round-trip), and possibly Settings (file history). One uploader handles all of them — drag and drop or click to browse, type validation by extension, progress indication during upload. The component is decoupled from the actual upload destination (consumer wires the upload handler).

## Acceptance criteria

- [x] `resources/js/components/MfFileDropzone.vue` exists with props per `docs/ux/components.md#mffiledropzone`:
  - `accept: string` — comma-separated list of extensions (e.g. `.csv` or `.csv,.pdf`).
  - `multiple?: boolean = false` — accept multiple files at once.
  - `maxSize?: number = 209715200` — bytes; default 200MB (PricingCustomExport is large).
  - `disabled?: boolean = false`.
- [x] Emits:
  - `@upload(files: File[])` — when valid files are dropped or selected.
  - `@progress(pct: number)` — caller-driven; the dropzone exposes a `setProgress(pct)` ref method consumers call to update the progress bar.
  - `@error(err: { code: 'invalid-type' | 'too-large' | 'multiple-not-allowed', message: string })` — fires for client-side validation failures.
- [x] Validation:
  - Filters dropped/selected files by extension against the `accept` prop. Files with disallowed extensions emit `@error('invalid-type')` and are not included in the `@upload` payload.
  - Files exceeding `maxSize` emit `@error('too-large')`.
  - When `multiple` is false and >1 file is provided, emit `@error('multiple-not-allowed')`.
- [x] Visual states:
  - Idle: dashed-border zone with cloud icon (`pi pi-cloud-upload`) and "Drop files here or click to browse" text.
  - Drag-over: solid border, brand-orange tint background.
  - Uploading: progress bar (PrimeVue `ProgressBar`) with the controlled `progress` value.
  - Success: green checkmark + filename(s); resets on next drop.
  - Error: red border + error message from the most recent `@error` emit.
- [x] Click-to-browse: hidden `<input type="file">` triggered when the user clicks anywhere in the zone.
- [x] Mobile-friendly: tap target ≥ 44px; the zone fills available width on `< 768px`.
- [x] Dark-mode safe.
- [x] Demo route OR Vue Test Utils test:
  - Programmatically drop a `.csv` file onto the zone; `@upload` fires with the file.
  - Drop a `.txt` file when `accept=".csv"`; `@error` fires with code `'invalid-type'`.
  - Drop a 250MB file; `@error` fires with code `'too-large'`.
- [x] `composer test` passes.

## Implementation notes

- Use PrimeVue's `FileUpload` component as a starting point but consider whether its UI matches the spec — its default look is button-heavy. If the styling fight isn't worth it, build a thin custom dropzone over `<input type="file">` + `dragover`/`drop` handlers; that's typically less code than rewiring PrimeVue's `FileUpload` slots. Either approach is acceptable; document the choice in the commit.
- The 200MB default is per the doc note about PricingCustomExport. Don't lower it without a UX-doc update.
- The component does NOT perform the upload itself. It hands files to the consumer via `@upload`; the consumer makes the actual XHR/Inertia call and reports back via `setProgress(pct)`.
- Upload progress: Inertia's `router.post(url, data, { onProgress: (e) => ... })` exposes the upload event; consumers wire it to `setProgress`.

## Out of scope

- Server-side upload handling (per-page tasks in phase 50/60).
- File preview thumbnails.
- Image-specific UX (we only handle CSV / PDF).
- A separate "completed uploads list" — consumer shows that separately.
