# Mythic Fox Games — SaaS Design Document

## Overview

A personal business management tool for Mythic Fox Games, a TCGPlayer seller of Magic: The Gathering, Lorcana, and Flesh & Blood cards. Built with Laravel 13 + Vue/Inertia, deployed to DigitalOcean at mythicfoxgames.com.

The system has three functional areas:

1. **Inventory Management** — Add cards to inventory as they are acquired
2. **Pricing Tool** — Refresh TCGPlayer listing prices from a fresh market-data export, apply rules, push back as a CSV
3. **Packing Slip Generator** — Generate custom packing slips from TCGPlayer order exports

A public-facing brand page at mythicfoxgames.com will serve as a trust indicator and future buy list platform. The admin SaaS is behind login.

---

## Build Order

1. **Packing Slip Generator** — Most self-contained, solves a daily pain point, concrete output to validate immediately
2. **Pricing Tool** — Stateless input/output by design, depends on the catalog tables but not on the order side
3. **Inventory Management** — Most complex, requires the full catalog plus the Add Cards UX

---

## Schema documents

The data model is split across two focused docs. Each is the single source of truth for its area — no duplication here.

- **[catalog-schema.md](catalog-schema.md)** — `products`, `sets`, `cards`, `inventory` tables; pricing rules and the dual-input pricing algorithm; PricingCustomExport import (catalog seed + market refresh); MyPricing bootstrap + reconciliation; pricing-export round-trip.
- **[order-schema.md](order-schema.md)** — `orders`, `order_items` tables; four-source import (OrderList, ShippingExport, PullSheet, PackingSlips PDF); status mapping; immutable-snapshot semantics.
- **[packingslip-spec.md](packingslip-spec.md)** — Layout, dimensions, and rendering for the generated packing slip PDF.

---

## Key design decisions

### TCGPlayer as the marketplace

The system is not a replacement for TCGPlayer. TCGPlayer remains the marketplace. Outbound flows (pricing CSV, eventual order updates) are designed to import back into TCGPlayer. The system improves workflows around TCGPlayer's limitations.

### No external APIs

No live API integrations. All TCGPlayer data flows through manual CSV/PDF exports and uploads. Catalog identity and market prices come from `PricingCustomExport` uploads (per product, ad-hoc). Inventory state and listing prices originate in this app and are pushed to TCGPlayer via the pricing-export round-trip.

### Mythic Fox is the source of truth for inventory

Inventory state lives here. Cards are added in the app first, prices computed locally, then a pricing CSV is exported and uploaded to TCGPlayer to update the listings. Orders flow back from TCGPlayer and decrement inventory. `MyPricing.csv` is used only for one-time bootstrap and periodic drift detection — not as a routine input.

### Auth — single admin user

One user (the owner). The standard `users` table from Laravel's vue-starter-kit + Fortify is used as-is. **No public-facing auth surface**:

- **Registration disabled** — remove `Features::registration()` from `config/fortify.php`. No `/register` route.
- **Password reset disabled** — remove `Features::resetPasswords()`. Use the artisan command instead.
- **Email verification disabled** — remove `Features::emailVerification()`.
- **2FA disabled** — remove `Features::twoFactorAuthentication()`.

User management via two artisan commands:

| Command | Purpose |
|---|---|
| `php artisan user:create {email} {name}` | Creates the admin user; prompts for password |
| `php artisan user:reset-password {email}` | Resets the password; prompts for new value |

These are the only ways to create or reset credentials. Droplet shell access is the recovery path.

Login route stays. Session lifetime: ~2 weeks (`SESSION_LIFETIME=20160`). Login rate limited at Fortify's default 5 attempts/min. Orphaned Inertia pages from the starter kit (Register, ForgotPassword, ResetPassword, TwoFactor) should be removed.

### Monetary values

**Storage**: integers in cents throughout. Decimals from TCGPlayer CSVs are parsed to cents at import; the pricing export formats cents back to two-decimal strings.

**Display**: US format with comma thousands and period decimal — `$0.20`, `$10.11`, `$1,234.56`. Always two decimal places, always with `$` prefix, never abbreviated.

- **Laravel side**: `Illuminate\Support\Number::currency($cents / 100, 'USD', 'en')` (Laravel 11+) — wrap in a single helper or value object so the format isn't restated everywhere.
- **Vue side**: a single `useMoney(cents)` composable returning the formatted string. Used in templates as `{{ useMoney(row.price) }}`.
- **PDF / packing slip**: same format, right-aligned in numeric table cells.

The format is hard-coded — no localization, no configurable currency.

---

## Files

### `files` table

Tracks every uploaded import and generated export — used by both the catalog import flow and the order import flow.

|Column|Type|Notes|
|---|---|---|
|id|bigint PK||
|type|string|`import` or `export`|
|file_path|string|Storage path (see §Path convention)|
|original_filename|string||
|uploaded_at|timestamp||
|expired_at|timestamp nullable|Set when the storage object is purged by the cleanup job. The row stays as an audit trail|
|created_at|timestamp||
|updated_at|timestamp||

### Storage drivers

| Environment | Driver | Notes |
|---|---|---|
| Local development | `local` (`storage/app/private`) | Same path structure as prod |
| Production | `s3` pointed at DigitalOcean Spaces | Bucket is **private** — no public ACL. Downloads via signed URLs only |

Customer addresses appear in the order CSVs — the bucket privacy is non-negotiable.

### Path convention

`{type}/{purpose}/{YYYY}/{MM}/{ulid}-{slug}.{ext}`

- `type` — `imports` or `exports`
- `purpose` — `orders`, `pricing`, `packing-slips`, etc.
- ULID is sortable and collision-free; slug is the slugified original filename.

Examples:

| Path | What |
|---|---|
| `imports/orders/2026/04/01HQX3K8YZ7ABC-orderlist.csv` | A TCGPlayer OrderList upload |
| `imports/orders/2026/04/01HQX3K8YZ7DEF-shippingexport.csv` | Same batch's ShippingExport |
| `imports/orders/2026/04/01HQX3K8YZ7GHI-pullsheet.csv` | Same batch's PullSheet |
| `imports/orders/2026/04/01HQX3K8YZ7JKL-packingslips.pdf` | Same batch's PDF |
| `imports/pricing/2026/04/01HQX3M5ABC-tcgplayer-pricingcustomexport.csv` | A PricingCustomExport upload |
| `imports/pricing/2026/04/01HQX3M5DEF-tcgplayer-mypricing.csv` | A MyPricing upload (bootstrap or reconciliation) |
| `exports/pricing/2026/04/01HQX3N7XYZ-mythic-fox-pricing.csv` | A pricing-export download |

Packing slips are not stored as files — they're rendered on demand from order data and printed directly from the browser (see [packingslip-spec.md](packingslip-spec.md)).

### Retention

| Path prefix | Retention | Rationale |
|---|---|---|
| `imports/...` | 90 days (loose — see below) | Working files, no audit value past a few months |
| `exports/pricing/...` | Forever | Record of what we set prices to — useful for margin/audit retrospectives |

The 90-day window is **not exact**. The cleanup job runs **weekly**, so an import file may live 90–96 days before purging. Acceptable.

Cleanup job (Laravel scheduler, weekly):

1. Find all `files` rows with `path` starting `imports/`, `uploaded_at < now() - 90 days`, `expired_at IS NULL`.
2. Delete each storage object.
3. Set `expired_at = now()` on the row.

The row is **never** hard-deleted from the database. Lost the bytes, kept the audit trail.

---

## Stack & deployment

### Frameworks

- Laravel 13 (vue-starter-kit base)
- Inertia + Vue 3 + TypeScript
- Fortify for auth (configured per §Auth)
- Wayfinder for typed routes
- Pest for tests
- PrimeVue for UI components (per ux-design.md)
- Print CSS rendered in the browser for packing slips (per packingslip-spec.md) — no server-side PDF generation

### Local development

- macOS
- PostgreSQL via [DBngin](https://dbngin.com/)
- Local filesystem for `files`
- PHP / Composer / Node via Homebrew or system tools

### Production

- **Host**: DigitalOcean Droplet, managed by Laravel Forge
- **Web**: Nginx + PHP-FPM (Forge defaults)
- **Database**: PostgreSQL on the same droplet (single-tenant solo workload — managed DB is overkill for v1). Backups via nightly `pg_dump` to DO Spaces, run by the Laravel scheduler.
- **Files**: DigitalOcean Spaces (private bucket)
- **Queue worker**: Forge daemon
- **Scheduler**: Forge cron (`* * * * * php artisan schedule:run`)
- **SSL**: Let's Encrypt via Forge
- **Domain**: already secured

### CI / CD

- GitHub Actions runs Pest + Pint on every PR.
- Forge auto-deploys on push to `main`. Standard Forge deploy script: `composer install`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan optimize:clear`.
- No staging environment. Local is the dev environment; prod is the only deployed one.

---

## Public Website (mythicfoxgames.com)

- Brand/trust page for Mythic Fox Games
- Admin login routes to the SaaS dashboard
- Future: buy list for purchasing collections from the public
- Not a storefront — TCGPlayer remains the sales channel
