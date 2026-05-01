---
id: "80-001"
title: "Configure Laravel Forge site (deploy script, queue daemon, scheduler, SSL, env vars)"
status: pending
phase: "80-deploy"
size: L
depends_on: ["80-002", "80-003", "phase:00-foundation"]
references:
  - docs/saas-design.md#stack--deployment
  - docs/saas-design.md#production
  - docs/saas-design.md#scheduled-jobs
  - docs/saas-design.md#ci--cd
  - AGENTS.md
---

## Goal

**This is an operator-manual task. The autonomous agent should mark it `blocked` and surface it to the operator for sign-off.**

Configure the Laravel Forge site so production runs the app correctly: GitHub-connected site, deploy script, queue worker daemon, scheduler cron, Let's Encrypt SSL, environment variables, and auto-deploy on push to `main`. This is mostly clicking through the Forge UI; the task captures the runbook so the operator can confirm each step. Per [saas-design.md §Production](../../docs/saas-design.md#production) and [§CI / CD](../../docs/saas-design.md#ci--cd).

Depends on `80-002` (droplet provisioned with Chrome, PHP extensions, Postgres) and `80-003` (DO Spaces bucket exists) — the env vars and disk references in this task assume those are in place.

## Acceptance criteria

Operator runbook — confirm each step:

- [ ] **Site created in Forge** pointing at the provisioned droplet (`80-002`), domain `mythicfoxgames.com`, web directory `/public`, PHP version matching local dev.
- [ ] **Repository connected**: GitHub repo, branch `main`, "Install Composer Dependencies" enabled.
- [ ] **Deploy script** (Forge UI → Site → Deploy Script) reads:
  ```
  cd $FORGE_SITE_PATH
  git pull origin $FORGE_SITE_BRANCH
  $FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev
  ( flock -w 10 9 || exit 1
      echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
  npm ci
  npm run build
  $FORGE_PHP artisan migrate --force
  $FORGE_PHP artisan optimize:clear
  $FORGE_PHP artisan optimize
  ```
  Per [§CI / CD](../../docs/saas-design.md#ci--cd).
- [ ] **Quick deploy enabled** (auto-deploy on push to `main`).
- [ ] **Environment variables** set in Forge UI → Site → Environment, mirroring `.env.example` from `00-004` with production values:
  - [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://mythicfoxgames.com`, `APP_KEY` generated (`php artisan key:generate --show` and paste).
  - [ ] `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (matches the Postgres role created in `80-002`).
  - [ ] `SESSION_LIFETIME=20160` (~2 weeks per [saas-design.md §Auth](../../docs/saas-design.md#auth--single-admin-user)).
  - [ ] `SESSION_DRIVER=database` (or `redis` if Redis ends up provisioned in `80-002`).
  - [ ] `QUEUE_CONNECTION=database` (or `redis` if applicable).
  - [ ] `FILESYSTEM_DISK=spaces`.
  - [ ] `DO_SPACES_KEY`, `DO_SPACES_SECRET`, `DO_SPACES_REGION`, `DO_SPACES_BUCKET`, `DO_SPACES_ENDPOINT` — values from the bucket created in `80-003`.
  - [ ] `TCGPLAYER_SELLER_ID`, `TCGPLAYER_SELLER_SLUG` — production seller identity.
  - [ ] `BRAND_NAME`, `BRAND_CONTACT_EMAIL` — production brand values.
  - [ ] `LOG_CHANNEL=stack`, `LOG_LEVEL=info`.
- [ ] **Queue daemon** configured in Forge UI → Site → Queue:
  - [ ] Connection: `database` (or `redis`).
  - [ ] Queue: `default`.
  - [ ] Timeout: 600 seconds (Browsershot scrapes can be slow; backup dumps slower).
  - [ ] Daemon starts on boot; Forge handles `supervisor` config.
- [ ] **Scheduler cron** added in Forge UI → Server → Scheduler: `* * * * * php artisan schedule:run` running as the site user. Per [§Scheduled jobs](../../docs/saas-design.md#scheduled-jobs).
- [ ] **SSL certificate** issued via Forge UI → Site → SSL → Let's Encrypt for `mythicfoxgames.com` (and `www.mythicfoxgames.com` if the apex/www split applies). Auto-renewal active.
- [ ] **Force HTTPS** enabled in Forge UI → Site → Meta.
- [ ] **First manual deploy** triggered (`Deploy Now`); deploy log shows green; site loads at `https://mythicfoxgames.com`; `/login` renders; admin user can log in (created via `php artisan user:create` from `00-003` over SSH).
- [ ] **Auto-deploy verified**: a no-op commit pushed to `main` triggers a deployment automatically and completes successfully.
- [ ] **Scheduled jobs verified** by SSH-ing in and running each manually:
  - [ ] `php artisan files:purge` runs without error (no-op on a fresh DB).
  - [ ] `php artisan seller-stats:refresh` runs and updates the `seller_stats` row.
  - [ ] `php artisan db:backup` runs and uploads a dump to `backups/db/...` on the Spaces bucket; verify the object appears in the DO control panel.
- [ ] **Rollback playbook documented** (a short note in the project's operator runbook or pinned somewhere outside the droplet, per [§Things to consider](../../docs/saas-design.md#things-to-consider)): `git revert <bad-sha> && git push origin main` triggers a redeploy; if that fails, `Forge UI → Site → Deployments → click prior deployment → Redeploy`.
- [ ] Operator confirms all of the above in the task's status note before flipping `status: complete`.

## Implementation notes

- **Forge UI is the source of truth** for site config. There is no IaC (Terraform / Ansible) for v1 — the operator clicks through and confirms.
- **`.env` does NOT live in git**. Forge stores it on the droplet at `/home/forge/mythicfoxgames.com/.env`. Editing happens via Forge UI, never via SSH (Forge will overwrite SSH edits on next config push).
- **First-time admin user** must be created over SSH after the first deploy: `cd /home/forge/mythicfoxgames.com && php artisan user:create josh@mythicfoxgames.com "Josh Evensen"` (per `00-003`).
- **GitHub Actions** running Pest + Pint on every PR is a separate task assumed already done in `phase:00-foundation` (`00-006`). Forge auto-deploy is independent — it deploys regardless of CI status. Operator discipline (don't merge red PRs) bridges the gap. Per [§CI / CD](../../docs/saas-design.md#ci--cd).
- **No staging environment** is intentional per [§CI / CD](../../docs/saas-design.md#ci--cd). Local + prod only.
- **Browsershot's Chrome dependency** lives in `80-002`; this task only verifies the scraper command runs, not the underlying install.

## Out of scope

- Provisioning the droplet itself (Chrome, PHP, Postgres) — that's `80-002`.
- Creating the DO Spaces bucket — that's `80-003`.
- IaC / Terraform — explicitly v2+.
- Setting up a staging environment — explicitly excluded by [§CI / CD](../../docs/saas-design.md#ci--cd).
- External uptime monitoring (UptimeRobot etc.) — flagged in [§Things to consider](../../docs/saas-design.md#things-to-consider) but not part of this task.
