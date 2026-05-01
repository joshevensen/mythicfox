---
id: "00-001"
title: "Switch local development DB from SQLite to PostgreSQL"
status: pending
phase: "00-foundation"
size: S
depends_on: []
references:
  - docs/saas-design.md#stack--deployment
  - docs/saas-design.md#local-development
---

## Goal

Production runs PostgreSQL on a DigitalOcean droplet. Local dev should match so migrations, JSON columns, and Postgres-specific behavior are exercised the same way in both environments. The Laravel starter ships with SQLite; we switch it now before any models are written.

## Acceptance criteria

- [ ] `.env.example` defaults to `DB_CONNECTION=pgsql` with `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=mythicfox`, `DB_USERNAME`=postgres and `DB_PASSWORD` is null.
- [ ] `.env` updated to match (developer's local Postgres credentials).
- [ ] `database/database.sqlite` removed and `composer.json` `post-create-project-cmd` no longer touches the SQLite file.
- [ ] `php artisan migrate:fresh` succeeds against a local Postgres database (created via DBngin or equivalent).
- [ ] All existing Fortify migrations (users, sessions, cache, jobs, two-factor columns) apply cleanly on Postgres.
- [ ] `php artisan test` still passes — set `DB_CONNECTION=pgsql` in `phpunit.xml` test env, or use a dedicated `mythicfox_test` database (preferred). Document the choice in this task's commit message.
- [ ] `composer test` passes.

## Implementation notes

- The doc recommends [DBngin](https://dbngin.com/) for local Postgres on macOS. Install instructions go in `README.md` if it doesn't already mention it (don't create a new doc for this).
- Use a separate `mythicfox_test` database for tests so Pest can `RefreshDatabase` without nuking dev data. Configure in `phpunit.xml` via `<env name="DB_DATABASE" value="mythicfox_test"/>`.
- Don't drop the existing `database/migrations/*` files; they target dialect-agnostic schema and work fine on Postgres.
- If Fortify's `two_factor_*` migration uses a column type that needs adjustment for Postgres, fix it in place — we'll be removing 2FA in `00-002` anyway, but the migration must still run cleanly during the brief window before that task ships.

## Out of scope

- Removing the 2FA columns themselves (that's `00-002`).
- Production database setup (that's phase 80).
- Backups via `pg_dump` (that's a phase 70/80 task).
