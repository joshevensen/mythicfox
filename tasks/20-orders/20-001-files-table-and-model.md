---
id: "20-001"
title: "Create `files` table, model, and storage path helper"
status: pending
phase: "20-orders"
size: M
depends_on: ["phase:00-foundation"]
references:
  - docs/saas-design.md#files
  - docs/saas-design.md#path-convention
  - docs/saas-design.md#retention
  - docs/order-schema.md#1-persist-files
---

## Goal

The `files` table is the audit trail for every uploaded import (orders, pricing) and every generated export (pricing). The order import flow's first step is "persist files" — so this table must exist before any importer can be built. It is shared with phase 10 (catalog/pricing imports) but lives here because phase 20 is the first consumer to actually write rows.

## Acceptance criteria

- [ ] Migration `create_files_table` creates a table with the columns documented in `docs/saas-design.md#files`: `id`, `type` (string, `import` or `export`), `file_path` (string), `original_filename` (string), `uploaded_at` (timestamp), `expired_at` (timestamp nullable), `created_at`, `updated_at`.
- [ ] An index exists on `(type, uploaded_at)` to support the weekly cleanup job query (`type = 'import' AND uploaded_at < now() - 90 days AND expired_at IS NULL`).
- [ ] `App\Models\File` Eloquent model exists with `$fillable` matching the columns above and a casts entry for `uploaded_at` / `expired_at` as `datetime`.
- [ ] A `FilePath` helper (e.g. `App\Support\FilePath` static class or invokable) generates paths matching the `{type}/{purpose}/{YYYY}/{MM}/{ulid}-{slug}.{ext}` convention from `docs/saas-design.md#path-convention`. Inputs: type (`imports`/`exports`), purpose (`orders`/`pricing`/etc.), original filename. Output: the full storage-relative path string. ULIDs come from `Illuminate\Support\Str::ulid()`; the slug from `Str::slug(pathinfo($name, PATHINFO_FILENAME))`.
- [ ] Unit test for `FilePath` covers: correct year/month segmentation, ULID present, slug derived from filename, extension preserved, special characters in original filename normalized.
- [ ] Pest feature test asserts `App\Models\File::create([...])` round-trips and the `(type, uploaded_at)` index is queryable.
- [ ] `composer test` passes.

## Implementation notes

- Generate the model + migration with `php artisan make:model File -m`.
- The `type` column stays a plain string (not enum) — the doc explicitly enumerates only two values today, but keeping it open avoids a migration if a third type ever appears.
- Storage disk selection (local vs `spaces`) is **not** this task's concern — `00-004` already configured both disks. The importers in later tasks pick a disk and call `Storage::disk(...)->putFileAs($path, ...)`; this task only defines the path string and DB row.
- The cleanup job that purges expired imports lives in phase 70 (`files:purge`). Don't write it here — but the index must already support its query.

## Out of scope

- The cleanup/retention job (phase 70).
- Signed URL helpers for downloading from the private Spaces bucket (phase 70 / 80).
- Any UI surfacing the `files` table (no admin UI is planned for it).
