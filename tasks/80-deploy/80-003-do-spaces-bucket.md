---
id: "80-003"
title: "Create DigitalOcean Spaces bucket and IAM credentials (private, signed-URL only)"
status: pending
phase: "80-deploy"
size: M
depends_on: ["phase:00-foundation"]
references:
  - docs/saas-design.md#files
  - docs/saas-design.md#storage-drivers
  - docs/saas-design.md#path-convention
  - docs/saas-design.md#production
---

## Goal

**This is an operator-manual task. The autonomous agent should mark it `blocked` and surface it to the operator for sign-off.**

Create the DigitalOcean Spaces bucket that holds production file artifacts (order/pricing import CSVs, pricing exports, nightly DB backups) and provision the IAM credentials the app uses to read/write it. Per [saas-design.md §Storage drivers](../../docs/saas-design.md#storage-drivers), the bucket is **private** — no public ACL, downloads via signed URLs only — because the order CSVs contain customer addresses.

## Acceptance criteria

Operator runbook — confirm each step:

- [ ] **DO Spaces bucket created** in the DigitalOcean control panel:
  - [ ] Region: same as the droplet (`80-002`) for low-latency uploads. Recommend `nyc3`.
  - [ ] Name: `mythicfoxgames` (or `mythicfox-prod` — pick one and document; the bucket name is the value of `DO_SPACES_BUCKET` in `.env`).
  - [ ] **File listing access**: `Restrict File Listing` enabled (private bucket per [§Storage drivers](../../docs/saas-design.md#storage-drivers)).
  - [ ] **CDN**: disabled. Customer addresses are in the import CSVs; CDN caching of private objects is the wrong tradeoff.
- [ ] **IAM credentials (Spaces access key) created** in DO control panel → API → Spaces Keys:
  - [ ] Key name: `mythicfox-app` (descriptive — there's no scoping per-bucket on DO Spaces keys; this is just for the operator's audit trail).
  - [ ] Access Key + Secret recorded in the operator's password manager and pasted into Forge env vars (`DO_SPACES_KEY`, `DO_SPACES_SECRET`) per `80-001`.
  - [ ] Secret is shown **once** in the DO UI — copy it immediately and don't lose it.
- [ ] **Endpoint and region recorded** for the `.env`:
  - [ ] `DO_SPACES_ENDPOINT=https://{region}.digitaloceanspaces.com` (e.g. `https://nyc3.digitaloceanspaces.com`).
  - [ ] `DO_SPACES_REGION={region}` (e.g. `nyc3`).
- [ ] **Bucket privacy verified**: from a non-authenticated browser, `https://{bucket}.{region}.digitaloceanspaces.com/imports/orders/2026/04/some-test-file.csv` returns 403 Forbidden, NOT a file or directory listing. (Operator can pre-seed a test file via the DO UI to confirm.)
- [ ] **Signed URL flow verified**: from a SSH session on the droplet after `80-001` is complete, run `php artisan tinker` and execute `Storage::disk('spaces')->put('test/hello.txt', 'hello')` then `Storage::disk('spaces')->temporaryUrl('test/hello.txt', now()->addMinutes(5))`. Open the URL in a browser — file downloads. Wait 5 minutes, retry — 403. Then `Storage::disk('spaces')->delete('test/hello.txt')`.
- [ ] **CORS configuration** (DO control panel → bucket → Settings → CORS): not required for v1. The app proxies all Spaces access through signed URLs minted by the Laravel backend; the browser doesn't `fetch` Spaces directly. Skip unless a future task needs direct browser uploads.
- [ ] **Bucket lifecycle policies**: not configured at the bucket level. Retention is enforced by the `files:purge` job (`70-004`) for `imports/...` and the backup-cleanup step in `70-005` for `backups/db/...`. DO Spaces doesn't currently support S3-style lifecycle rules anyway; application-side enforcement is the only path.
- [ ] **Path convention pre-validated**: operator manually creates a single empty placeholder folder (or uploads a `.keep` file) at each top-level prefix the app uses, just to make the bucket structure visible in the DO UI:
  - [ ] `imports/.keep`
  - [ ] `exports/.keep`
  - [ ] `backups/.keep`
  - Per [§Path convention](../../docs/saas-design.md#path-convention) — full paths are `{type}/{purpose}/{YYYY}/{MM}/{ulid}-{slug}.{ext}`.
- [ ] **Forge env vars updated** in `80-001` with the values from above (this task produces the values; `80-001` consumes them).
- [ ] Operator confirms all of the above in the task's status note before flipping `status: complete`.

## Implementation notes

- DO Spaces is S3-compatible, so the Laravel `s3` filesystem driver works directly per [§Storage drivers](../../docs/saas-design.md#storage-drivers). The `spaces` disk config in `config/filesystems.php` (added in `00-004`) reads from these env vars.
- **`use_path_style_endpoint => false`** is required for DO Spaces (already set in `00-004`); path-style URLs don't work on DO's edge.
- **Don't enable the CDN.** It seems harmless but it caches objects at edge nodes — and signed-URL invalidation isn't reliable across CDN caches. Direct-to-origin reads are fine for the app's traffic level.
- **Spaces keys are global to the DO account**, not bucket-scoped. There's no per-bucket IAM policy. If the operator's account holds other buckets, this key can read them too — that's a DO platform limitation, not something to mitigate in this task.
- **Backups bucket separation**: technically the nightly DB backup (`70-005`) and the import/export files share the same bucket under different top-level prefixes (`backups/...` vs `imports/...` vs `exports/...`). That's intentional — one bucket, easier credential management.
- The actual deploy of the env vars into Forge happens in `80-001`. This task's deliverable is the bucket existing + the credentials being known.

## Out of scope

- Creating the Forge site / installing env vars — that's `80-001`.
- Provisioning the droplet — that's `80-002`.
- Cross-region replication / off-DO backup sync — single-region for v1 per [saas-design.md §Things to consider](../../docs/saas-design.md#things-to-consider).
- Configuring CORS for direct-browser uploads — not needed; the app proxies all access.
- Bucket-level lifecycle / TTL policies — not supported by DO Spaces; application-side enforcement only.
