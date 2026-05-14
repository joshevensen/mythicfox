---
id: "70-005"
title: "Nightly DB backup job (pg_dump → DO Spaces)"
status: complete
phase: "70-jobs"
size: M
depends_on: ["phase:00-foundation"]
references:
  - docs/saas-design.md#scheduled-jobs
  - docs/saas-design.md#production
  - docs/saas-design.md#things-to-consider
---

## Goal

Take a nightly logical backup of the production PostgreSQL database via `pg_dump` and upload the resulting compressed file to DO Spaces (private bucket). Per [saas-design.md §Production](../../docs/saas-design.md#production), backups are the only persistence-safety net against droplet loss; per [§Things to consider](../../docs/saas-design.md#things-to-consider), they need a tested restore path — this task delivers the dump-and-upload half; the restore drill is operator-side.

## Acceptance criteria

- [x] `App\Jobs\BackupDatabase` job exists, implements `ShouldQueue`.
- [x] `php artisan db:backup` console command invokes the job.
- [x] Scheduler entry in `routes/console.php` (or `app/Console/Kernel.php`) runs `db:backup` **nightly** (suggest 2:00 AM server time — pick one and document in the commit). Per [§Scheduled jobs](../../docs/saas-design.md#scheduled-jobs).
- [x] The job:
  - [x] Builds a `pg_dump` invocation against the configured `pgsql` connection (host/port/db/user from `config('database.connections.pgsql')`; password supplied via `PGPASSWORD` env on the subprocess to avoid leaking it via `ps`).
  - [x] Uses `--format=custom --no-owner --no-privileges` for a portable, restorable dump.
  - [x] Pipes output to a temp file, e.g. `storage/app/backups/mythicfox-{YYYYMMDD-HHMM}.dump`.
  - [x] Uploads the temp file to the `spaces` disk at path `backups/db/{YYYY}/{MM}/mythicfox-{YYYYMMDDHHMM}.dump`.
  - [x] Deletes the local temp file after a successful upload.
  - [x] Logs `Log::info('DB backup uploaded', ['path' => ..., 'bytes' => ...])` on success.
- [x] On failure (pg_dump non-zero exit, upload failure):
  - [x] The local temp file is cleaned up (no orphaned dumps in `storage/app/backups/`).
  - [x] `Log::error(...)` records the failure.
  - [x] The exception bubbles so the queue worker / scheduler records it as failed (no silent loss).
- [x] **Old-backup retention** (in this same job or as a follow-up step): backups older than **30 days** under `backups/db/...` on the `spaces` disk are deleted. 30 days of nightly dumps is sufficient for point-in-time recovery within a reasonable window without unbounded storage growth.
- [x] Pest feature tests cover:
  - [x] **Success path**: mock the subprocess runner so it produces a small fake dump file at the expected temp path; run the job against `Storage::fake('spaces')`; assert the file exists at the expected `backups/db/YYYY/MM/...dump` path on the fake disk; assert the temp file is cleaned up.
  - [x] **Failure path**: mock the runner to exit non-zero; assert the job throws (or fails the queue), the temp file is cleaned up, and no upload was attempted.
  - [x] **Retention**: pre-seed the fake spaces disk with dump files at `backups/db/...` dated 31 days ago; run the job; assert the old file was deleted and the just-uploaded file remains.
- [x] `composer test` passes.

## Implementation notes

- `pg_dump` must be installed on the droplet — that is part of `80-002` (provisioning). This task assumes it's there in production. In the dev environment it's installed via Homebrew alongside Postgres.
- Wrap the subprocess via `Symfony\Component\Process\Process` so timeouts and exit codes are first-class. Set a generous timeout (10 minutes) — small databases dump in seconds, but it'd grow.
- Pass the password via `Process->setEnv(['PGPASSWORD' => $password])` rather than the deprecated `~/.pgpass` file or a connection string with password embedded.
- Compression: `--format=custom` already compresses (zstd or gzip depending on libpq version). Don't double-compress with `gzip`.
- Encryption-at-rest: DO Spaces provides server-side encryption by default. Explicit client-side encryption is out of scope for v1.
- For the restore drill mentioned in [§Things to consider](../../docs/saas-design.md#things-to-consider): that's an operator runbook, not a job. Include a short note in the task's commit message linking to the operator drill steps if they exist.
- Use `now()->format('Ymd-Hi')` for the local temp filename and `now()->format('YmdHi')` for the spaces filename (no hyphen, sortable). Pick one convention and stick with it; both are fine.

## Out of scope

- Restoring from backup (operator-manual quarterly drill per [§Things to consider](../../docs/saas-design.md#things-to-consider)).
- Cross-region replication of backups (DO Spaces is region-pinned; v1 accepts that).
- Encrypting backups client-side before upload — server-side encryption only.
- Backing up the `files` bucket itself — those files are working artifacts, not source-of-truth data; the database is the source of truth.
- Differential / WAL-based backups — full nightly logical dumps are sufficient for the data volume.
