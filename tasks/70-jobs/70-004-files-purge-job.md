---
id: "70-004"
title: "Weekly file-cleanup job (90-day import retention, audit row preserved)"
status: pending
phase: "70-jobs"
size: M
depends_on: ["phase:20-orders"]
references:
  - docs/saas-design.md#retention
  - docs/saas-design.md#files-table
  - docs/saas-design.md#scheduled-jobs
---

## Goal

Purge stale `imports/...` storage objects on a 90-day rolling window so the DO Spaces bucket doesn't accumulate forever. The `files` row is **never** hard-deleted — only the underlying storage object — and `expired_at` is set on the row as the audit trail. `exports/pricing/...` is retained forever and never touched by this job. Per [saas-design.md §Retention](../../docs/saas-design.md#retention).

## Acceptance criteria

- [ ] `App\Jobs\PurgeExpiredFiles` job exists, implements `ShouldQueue`.
- [ ] `php artisan files:purge` console command invokes the job.
- [ ] Scheduler entry in `routes/console.php` (or `app/Console/Kernel.php`) runs `files:purge` **weekly** (suggest Sunday at 3:00 AM server time — pick one and document in the commit). Per [§Scheduled jobs](../../docs/saas-design.md#scheduled-jobs).
- [ ] The job selects `files` rows matching ALL of:
  - [ ] `file_path` starts with `imports/` (case-sensitive prefix match).
  - [ ] `uploaded_at < now() - 90 days`.
  - [ ] `expired_at IS NULL`.
- [ ] For each matched row, the job:
  - [ ] Deletes the storage object via the configured disk (`Storage::disk('spaces')->delete($file->file_path)` in prod, `local` disk in dev/test).
  - [ ] Sets `expired_at = now()` on the `files` row and saves.
  - [ ] Does **not** delete the row.
- [ ] Storage-delete failures (object already missing, transient S3 error) do not abort the whole job. The job logs the failure for that file, leaves `expired_at` NULL on that row (so the next run retries), and continues to the next.
- [ ] Files with `file_path` starting `exports/...` are **never** matched, even if older than 90 days.
- [ ] Files with `expired_at` already set are skipped (idempotent — the next run is a no-op for already-purged rows).
- [ ] Pest feature tests cover:
  - [ ] **Success path**: seed a `files` row with `file_path = 'imports/orders/2025/01/...csv'` and `uploaded_at = now()->subDays(91)`; put a fake file at that path on the `local` disk via `Storage::fake()`; run the job; assert the storage object is gone, `expired_at` is set on the row, and the row still exists.
  - [ ] **Boundary**: row with `uploaded_at = now()->subDays(89)` is NOT matched.
  - [ ] **Exports preserved**: row with `file_path = 'exports/pricing/...csv'` and `uploaded_at = now()->subYears(2)` is NOT matched.
  - [ ] **Idempotency**: row with `expired_at` already set is skipped (no second `delete()` call on the disk — assert via `Storage::fake()` events or counter).
  - [ ] **Storage failure tolerance**: if the disk's `delete()` throws on one row, the job continues with the next row and the throwing row's `expired_at` remains NULL.
  - [ ] **No imports to purge**: empty result set runs cleanly without errors.
- [ ] `composer test` passes.

## Implementation notes

- Use the `files` Eloquent model from phase 20. If the model uses a different column name than `file_path` or `uploaded_at`, match the actual schema.
- Process in chunks (`->chunkById(500)`) so a runaway accumulation can't OOM the worker.
- The cleanup window is "90 days" *measured from `uploaded_at`*. A weekly cadence means files live 90–96 days before purging, per [§Retention](../../docs/saas-design.md#retention) — that's intentional, not a bug.
- The `expired_at` column was specified in [§`files` table](../../docs/saas-design.md#files-table); it was added during the `files` migration in phase 20. If it isn't there, add it as a small migration in this task.
- Logging: `Log::info('Purged file', ['file_id' => $file->id, 'path' => $file->file_path])` on success; `Log::warning('Purge failed', [...])` on storage failure. No outbound notifications.

## Out of scope

- Restoring purged files from a backup (purges are permanent).
- A "soft-delete window" UI on the Settings page showing files about to be purged — not in v1.
- Configurable retention durations per file type — 90 days is hard-coded per the spec.
- Touching `exports/...` paths — those are retained forever.
- Notifying the operator on purge — silent operation; the audit trail is the `expired_at` column.
