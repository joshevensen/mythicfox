---
id: "00-002"
title: "Strip Fortify down to single-user login (disable 2FA, registration, password reset)"
status: pending
phase: "00-foundation"
size: S
depends_on: ["00-001"]
references:
  - docs/saas-design.md#auth--users
  - docs/ux/login.md
  - docs/ux/settings.md#section-account
---

## Goal

This is a single-operator personal tool. Fortify ships with registration, password reset, email verification, and 2FA — all of which are dead weight here and create surface area we don't want. Reduce Fortify to the minimum: email/password login, logout, session management. Password recovery happens via the Artisan command added in `00-003`.

## Acceptance criteria

- [ ] `config/fortify.php` `features` array contains only what login needs. Specifically: remove `Features::registration()`, `Features::resetPasswords()`, `Features::emailVerification()`, `Features::twoFactorAuthentication()`. Keep `Features::updatePasswords()` and `Features::updateProfileInformation()` (used by Settings page in phase 50).
- [ ] Two-factor columns dropped from the `users` table via a new migration. Drop columns: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`. The original `2025_08_14_170933_add_two_factor_columns_to_users_table.php` migration stays in place (so existing dev DBs don't break on `migrate`); the new migration is the down-step.
- [ ] Any references to 2FA in the Vue frontend (Inertia pages, components, route helpers) removed. Search for `two-factor`, `twoFactor`, `recovery-code` and remove the associated pages/routes.
- [ ] Any references to password reset / forgot password removed from the login Vue page and routes.
- [ ] `routes/settings.php` no longer exposes 2FA setup routes. Profile update and password change routes remain.
- [ ] After the change, `/login` shows email + password + "Remember me" only. No "Forgot password?" link, no "Register" link.
- [ ] `php artisan migrate:fresh` succeeds.
- [ ] `composer test` passes.

## Implementation notes

- Wayfinder may have generated route helpers for the disabled routes in `resources/js/routes/` — regenerate after removing the routes or delete the now-stale files.
- The `User` model's `HasApiTokens`/`TwoFactorAuthenticatable` traits should be removed if present.
- Don't delete the `users` table itself or remove the email/password columns. The single admin user is created via `00-003`.

## Out of scope

- Creating the admin user (that's `00-003`).
- Login page styling / branding (that's `40-public-pages`, the login page redesign).
