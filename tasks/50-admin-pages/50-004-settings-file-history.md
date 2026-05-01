---
id: "50-004"
title: "Settings — File History section (paginated audit log)"
status: pending
phase: "50-admin-pages"
size: M
depends_on: ["50-001", "50-003", "phase:30-components"]
references:
  - docs/ux/settings.md#section-file-history
  - docs/ux/settings.md#data
  - docs/ux/settings.md#states
  - docs/saas-design.md
---

## Goal

The second section of the Settings page: a paginated audit log of every CSV/PDF imported and every export generated. Filterable by direction / purpose / date range, sortable by upload time, with a per-row download icon for non-expired files. Sits at anchor `#file-history` on the Settings page; the section is rendered inside the Settings Inertia page produced by `50-003`.

## Acceptance criteria

- [ ] The `#file-history` anchor on `/settings` renders a section with header `"File History"`.
- [ ] Above the table, an `MfFilterPanel` exposes the filters from `settings.md §Filter panel`:
  - **Direction** — multi-select: `import`, `export` (default: all).
  - **Purpose** — multi-select populated from `DISTINCT` second-segment values of `file_path` (default: all).
  - **Date range** — `MfDatePicker` range (default: all time).
  - **Hide expired** — toggle (default off — expired files visible).
- [ ] An `MfTable` in `lazy` mode renders the audit-log rows with **page size override = 20** (vs the default 50). Columns:
  - **Filename** — `original_filename` — sortable.
  - **Direction** — `type` (`import` / `export`) — sortable.
  - **Purpose** — derived from `file_path` segment 2 — sortable.
  - **Uploaded** — `uploaded_at` formatted `MMM D, YYYY h:mma` — sortable, **default sort desc**.
  - **Status** — derived: green-tinted "Active" if `expired_at IS NULL`, else muted `"Expired {date}"` — sortable.
  - **(action)** — single right-edge icon column, no header.
- [ ] Per-row action: when `expired_at IS NULL`, render a download icon (`pi pi-download`). Clicking it generates a signed URL via the storage driver (S3-compatible signed URL in prod, direct path in local) and opens the URL in a new tab. Expired rows have empty action space (no icon).
- [ ] **No bulk actions** on this table.
- [ ] Pagination, sort, and filters all serialize to the URL query string per `ux-patterns.md §URL-driven state` so refresh and back-button preserve view state.
- [ ] Mobile (`< 768px`): the table switches to card-row layout via `MfTable`'s `mobile-row` slot per the example in `settings.md §Mobile layout`. Filter panel becomes a full-screen drawer; the "Hide expired" toggle stays inline above the cards.
- [ ] Empty states:
  - No files at all: `"No files yet — imports and exports will appear here."`
  - Filtered to zero rows: `"No files match these filters."` + Clear filters button.
- [ ] Download error toast: `"Couldn't generate download URL — file may be missing from storage."`
- [ ] Pest feature test `tests/Feature/Admin/Settings/FileHistoryTest.php` covers:
  - Unauthenticated visit redirects to `/login`.
  - Authenticated visit lists files seeded by factory, default-sorted by `uploaded_at` desc.
  - Filtering by `direction=export` returns only export rows.
  - The download endpoint returns a signed URL (200 + redirect or JSON body, per the implementation choice) for an active file and 404/410 for an expired file.
  - Empty state renders when no files exist.
  - Filtered-empty state renders with a Clear-filters affordance when filters return zero rows.
- [ ] `composer test` passes.

## Implementation notes

- The `files` table is owned by phase 10 / phase 20 (catalog/orders imports + exports write into it). This task only **reads** from `files` and exposes the download endpoint.
- "Purpose" is the second path segment of `file_path` (per `saas-design.md §Path convention`). Examples: `imports/orders/...` → "orders"; `exports/pricing/...` → "pricing"; `imports/packing-slips/...` → "packing-slips". Compute via SQL `split_part(file_path, '/', 2)` (Postgres) or in PHP at the controller layer.
- Download endpoint: `GET /settings/files/{file}/download` (auth-required). Generates a signed URL via the configured storage disk (`spaces` in prod, `local` in dev — both configured in `00-004`). Returns a redirect to that URL.
- File-not-found / expired download → respond with the expected status (404 or 410) and emit a toast in the Inertia error handler.
- Use `MfTable`, `MfFilterPanel`, `MfFilterChip`, `MfSearchInput` from phase 30 — do not re-implement table chrome.
- DB index: ensure `files (uploaded_at)` exists for the default sort. If the schema migration is owned by phase 10/20, this task may need to add an index migration; document the choice in the commit message.

## Out of scope

- The `files` table schema and the import/export flows that populate it (phase 10 / phase 20).
- Pricing Rules section (that's `50-003`).
- Seller Stats Scraper section (that's `50-005`).
- Bulk actions (intentionally absent).
- Manual file deletion / forced expiry (out of v1 scope).
- The cleanup job that flips `expired_at` and deletes storage objects (phase 70).
- A "Recent (last 5)" inline list above the paginated table (deferred per `settings.md §Things to consider`).
