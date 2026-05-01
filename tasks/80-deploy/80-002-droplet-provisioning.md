---
id: "80-002"
title: "Provision DigitalOcean droplet (Chrome, PHP extensions, PostgreSQL, pg_dump)"
status: pending
phase: "80-deploy"
size: L
depends_on: ["phase:00-foundation"]
references:
  - docs/saas-design.md#production
  - docs/saas-design.md#stack--deployment
  - docs/saas-design.md#scheduled-jobs
  - docs/saas-design.md#things-to-consider
---

## Goal

**This is an operator-manual task. The autonomous agent should mark it `blocked` and surface it to the operator for sign-off.**

Provision the DigitalOcean droplet that hosts the production app: register it with Laravel Forge, install headless Chrome (for the seller-stats Browsershot scraper), install required PHP extensions, install/configure PostgreSQL on the same droplet, and ensure `pg_dump` is on the path for the nightly backup job. Per [saas-design.md §Production](../../docs/saas-design.md#production), this is single-droplet, no managed DB — the operator owns droplet health.

## Acceptance criteria

Operator runbook — confirm each step:

- [ ] **Droplet created** in DigitalOcean: at minimum a 2GB / 1 CPU droplet (Browsershot pulls in Chrome which uses ~700MB disk + memory overhead per render — flagged in [§Things to consider](../../docs/saas-design.md#things-to-consider)). Recommend 4GB / 2 CPU for headroom.
- [ ] **Region** chosen close to the operator (NYC3 is the convention — keep DO Spaces in the same region per `80-003`).
- [ ] **OS**: Ubuntu LTS (matches what Forge expects).
- [ ] **Droplet connected to Forge** via the Forge UI → Servers → Connect to a Server flow. Forge installs:
  - [ ] Nginx + PHP-FPM with the latest stable PHP version matching local dev (per `00-001`).
  - [ ] Composer.
  - [ ] Node.js LTS + npm.
  - [ ] PostgreSQL (Forge offers it as a checkbox during server creation).
  - [ ] (Optional) Redis if `QUEUE_CONNECTION` / `SESSION_DRIVER` end up `redis` in `80-001`. Otherwise skip — `database` driver works fine for the workload.
- [ ] **PHP extensions verified** via `php -m` on the droplet — these MUST be present:
  - [ ] `pdo_pgsql` (Postgres driver per [§Production](../../docs/saas-design.md#production)).
  - [ ] `gd` or `imagick` (image handling for any logo / asset processing).
  - [ ] `bcmath` (monetary precision per [saas-design.md §Monetary values](../../docs/saas-design.md#monetary-values)).
  - [ ] `intl` (`Number::currency()` formatting).
  - [ ] `zip`, `mbstring`, `xml`, `curl`, `openssl`, `tokenizer`, `fileinfo` (Laravel baseline).
  - [ ] `pcntl`, `posix` (queue worker).
  - Forge installs most of these by default; verify and `apt install php{X.Y}-{ext}` any missing.
- [ ] **Headless Chrome installed** for Browsershot per [saas-design.md §Stack](../../docs/saas-design.md#stack--deployment):
  - [ ] `apt install google-chrome-stable` (or `chromium-browser`) plus the font/lib dependencies it pulls in.
  - [ ] Verified via `google-chrome --version` from the forge user's shell.
  - [ ] Browsershot smoke test: SSH in, run `php -r "require 'vendor/autoload.php'; echo Spatie\Browsershot\Browsershot::url('https://example.com')->bodyHtml();"` from the site directory after first deploy. Returns HTML without an exception.
  - [ ] Path to `npm` and `node` is set in Browsershot config if non-default (Forge installs Node via NVM under `/home/forge/.nvm/...`; Browsershot needs to know where it lives — set via `Browsershot::setNodeBinary(...)` in `config/services.php` or wherever the scraper job reads from, or set the `NODE_PATH` env in Forge).
- [ ] **`pg_dump` on the path** for the nightly backup job (`70-005`):
  - [ ] `which pg_dump` returns `/usr/bin/pg_dump` (or wherever Postgres put it).
  - [ ] Version matches the running Postgres server version (mismatch breaks `--format=custom` dumps).
- [ ] **PostgreSQL configured**:
  - [ ] Database `mythicfox` created.
  - [ ] DB user/role created with a strong password (recorded in the operator's password manager, not in git).
  - [ ] `pg_hba.conf` allows local socket connection from the forge user with password auth (Forge handles this by default).
  - [ ] Connection verified from the forge user: `psql -h 127.0.0.1 -U mythicfox -d mythicfox -c '\dt'` returns without error (empty result is fine — migrations run on first deploy).
- [ ] **Firewall** (Forge UI → Server → Network):
  - [ ] Port 22 (SSH) open from operator IPs (or anywhere if rotating IPs is impractical).
  - [ ] Ports 80, 443 open to anywhere.
  - [ ] Postgres port 5432 NOT exposed publicly (default Forge behavior; verify).
- [ ] **SSH keys** added to the forge user for the operator's primary machine. Operator confirms login: `ssh forge@<droplet-ip>` works without password.
- [ ] **Swap** configured (Forge offers a swap-file size option during server creation; 2GB swap is reasonable for a 4GB droplet — Browsershot peaks can exceed RAM briefly).
- [ ] **Server timezone** set to UTC (default; verify with `timedatectl`). Scheduled job times in [§Scheduled jobs](../../docs/saas-design.md#scheduled-jobs) are server-local; pick UTC and stay consistent.
- [ ] **Browsershot Chrome sandbox**: depending on Ubuntu version + Chrome version, Browsershot may need `->noSandbox()` to render. If so, document the decision in the scraper job (`70-003`) — running Chrome without sandbox on a single-tenant operator-only droplet has acceptable risk.
- [ ] Operator confirms all of the above in the task's status note before flipping `status: complete`.

## Implementation notes

- **Forge does most of this for you.** The "install Chrome" step is the only one that's not click-through — see [Spatie's Browsershot install docs](https://spatie.be/docs/browsershot/v3/requirements) for the canonical apt command.
- **Don't install MySQL** by accident — Forge offers it as a default. Pick Postgres explicitly.
- **PHP version pinning**: whatever version is in local dev (`00-001`). If local moves, plan a coordinated bump — Forge supports multiple PHP versions side-by-side.
- **Node version pinning**: Browsershot uses Puppeteer under the hood (newer Browsershot versions). Whatever Node LTS Forge installed should be fine; only intervene if Browsershot complains.
- **The Chrome sandbox issue** is the most common Browsershot gotcha on fresh Ubuntu droplets. If the smoke test throws `Failed to launch the browser process`, add `--no-sandbox --disable-setuid-sandbox` flags via `->setOption('args', ['--no-sandbox'])` in the scraper.
- **Local Postgres backup credentials** for `pg_dump`: the backup job (`70-005`) reads `DB_PASSWORD` and passes it via `PGPASSWORD` env to the subprocess. Forge's stored DB credentials must match what's in the site `.env`.

## Out of scope

- Forge site config (deploy script, env vars) — that's `80-001`.
- DO Spaces bucket creation — that's `80-003`.
- Configuring an external uptime monitor — flagged in [§Things to consider](../../docs/saas-design.md#things-to-consider) but not in this task.
- Creating the admin user (operator runs `php artisan user:create` after first deploy in `80-001`).
- High-availability / multi-droplet setup — explicitly single-droplet per [§Things to consider](../../docs/saas-design.md#things-to-consider).
