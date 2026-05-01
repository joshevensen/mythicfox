---
id: "00-006"
title: "Confirm Pest baseline and add GitHub Actions CI"
status: pending
phase: "00-foundation"
size: M
depends_on: ["00-001", "00-002", "00-003", "00-004", "00-005"]
references:
  - docs/saas-design.md#stack--deployment
---

## Goal

Every later task asserts "`composer test` passes" — but that command needs to actually be running on every push, not just locally. Stand up a GitHub Actions workflow now so regressions are caught before they hit `main` (which auto-deploys to prod via Forge). This is the last foundation task; once it ships, phase 10 begins.

## Acceptance criteria

- [ ] `.github/workflows/ci.yml` exists and runs on `push` and `pull_request` to any branch.
- [ ] The workflow:
  - Checks out the code.
  - Installs PHP 8.3 with required extensions (mbstring, intl, pdo_pgsql, redis).
  - Spins up a PostgreSQL service container and creates a `mythicfox_test` database.
  - Runs `composer install --no-interaction --prefer-dist`.
  - Runs `npm ci`.
  - Copies `.env.example` to `.env`, runs `php artisan key:generate`, `php artisan migrate --force`.
  - Runs `composer ci:check` (which already chains lint, format, types, and tests per the existing `composer.json` script).
- [ ] Workflow passes on a fresh branch with no other changes.
- [ ] A failing test makes the workflow fail (verify by intentionally breaking a test on a throwaway branch, confirming red, then reverting — the verify step doesn't need to be committed; document the result in the commit message).
- [ ] `README.md` has a one-line CI status badge near the top (optional but recommended).
- [ ] `composer test` passes locally.

## Implementation notes

- Use the official `shivammathur/setup-php@v2` action for PHP setup.
- Use `services: postgres:` in the workflow with healthcheck so migrations don't race the DB startup.
- The Browsershot/pdfparser autoload tests from `00-005` should pass in CI without Chrome installed — they only check class autoload, not rendering.
- Don't add a separate "deploy" job here. Forge handles deploy on `main` push independently. CI is purely for catching regressions before merge.
- Keep the workflow under ~80 lines. If it grows, split into reusable composite actions later.

## Out of scope

- Forge deploy automation (existing; not changing).
- Branch protection rules requiring CI to pass before merge (operator configures this in GitHub settings — note in the task commit message as a manual follow-up).
- Production environment configuration (phase 80).
