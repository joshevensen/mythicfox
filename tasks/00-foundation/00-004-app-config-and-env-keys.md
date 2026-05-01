---
id: "00-004"
title: "Add app config keys and `.env.example` entries for TCGPlayer, DO Spaces, and brand"
status: pending
phase: "00-foundation"
size: S
depends_on: []
references:
  - docs/saas-design.md#stack--deployment
  - docs/saas-design.md#seller-stats-scraper
  - docs/order-schema.md
  - docs/ux/public-homepage.md
---

## Goal

Centralize all environment-specific values that later tasks will need to read: TCGPlayer seller identity, DO Spaces credentials for production file storage, and the brand contact info that appears on packing slips and the public homepage. Doing this once here means every later task can `config('services.tcgplayer.seller_id')` without re-deciding the key name.

## Acceptance criteria

- [ ] `.env.example` contains, with empty placeholder values:
  - `TCGPLAYER_SELLER_ID=` (e.g. `623394e9`)
  - `TCGPLAYER_SELLER_SLUG=` (e.g. `Mythic-Fox-Games`)
  - `DO_SPACES_KEY=`
  - `DO_SPACES_SECRET=`
  - `DO_SPACES_REGION=` (e.g. `nyc3`)
  - `DO_SPACES_BUCKET=`
  - `DO_SPACES_ENDPOINT=` (e.g. `https://nyc3.digitaloceanspaces.com`)
  - `BRAND_NAME="Mythic Fox Games"`
  - `BRAND_CONTACT_EMAIL=josh@mythicfoxgames.com`
- [ ] `config/services.php` exposes a `tcgplayer` block with `seller_id`, `seller_slug`, and a derived `storefront_url` (`https://www.tcgplayer.com/sellers/{slug}/{id}`).
- [ ] `config/services.php` exposes a `do_spaces` block (or a new `config/storage.php` — pick one and document the choice in the commit) reading the five `DO_SPACES_*` env vars.
- [ ] `config/filesystems.php` adds a `spaces` disk configured as an `s3` driver pointed at the DO Spaces config above, with `'visibility' => 'private'` and `'use_path_style_endpoint' => false`.
- [ ] `config/app.php` (or a new `config/brand.php`) exposes `name` and `contact_email` from the `BRAND_*` env vars.
- [ ] All keys also appear in the developer's `.env` with placeholder/dev-safe values so the app boots locally.
- [ ] `php artisan config:clear && php artisan tinker` can resolve `config('services.tcgplayer.seller_id')` without error.
- [ ] `composer test` passes.

## Implementation notes

- Don't commit real credentials. The dev `.env` is gitignored; only `.env.example` is committed.
- The `spaces` filesystem disk won't be exercised locally (use `local` in dev) but its config must validate.
- TCGPlayer seller ID lowercase or uppercase? The public URL accepts both (`623394e9` and `623394E9`). Pick lowercase for consistency; the order-import logic in phase 20 will case-insensitive compare anyway.

## Out of scope

- Actually creating the DO Spaces bucket (operator does this manually; tracked in `80-deploy`).
- Wiring up Browsershot to use these (that's `00-005`).
- The seller-stats scraper itself (that's phase 70).
