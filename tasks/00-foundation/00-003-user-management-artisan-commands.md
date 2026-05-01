---
id: "00-003"
title: "Add `user:create` and `user:reset-password` Artisan commands"
status: pending
phase: "00-foundation"
size: S
depends_on: ["00-002"]
references:
  - docs/saas-design.md#auth--users
  - docs/ux/settings.md#section-account
---

## Goal

With registration and password reset disabled, the only paths to create or recover an admin account are Artisan commands run on the production droplet via SSH. These are the operator's lifeline if the password is lost — they must work, be tested, and be obvious to find.

## Acceptance criteria

- [ ] `php artisan user:create {email} {name}` exists. It prompts for a password (hidden input), confirms it, hashes with bcrypt, and creates the user. Refuses if a user with that email already exists. Refuses if more than zero users already exist (single-user invariant).
- [ ] `php artisan user:reset-password {email}` exists. It prompts for a new password (hidden input), confirms it, updates the user's `password` column. Refuses if the user doesn't exist.
- [ ] Both commands are registered in `app/Console/Commands/` (or wherever the project's existing command registration lives — check `bootstrap/app.php` and `routes/console.php`).
- [ ] Pest feature test for each command:
  - `user:create` happy path.
  - `user:create` rejects duplicate email.
  - `user:create` rejects when a user already exists.
  - `user:reset-password` happy path verifies new hash matches via `Hash::check`.
  - `user:reset-password` rejects unknown email.
- [ ] Tests use `RefreshDatabase` and the `mythicfox_test` Postgres database from `00-001`.
- [ ] `composer test` passes.

## Implementation notes

- Use `$this->secret('Password: ')` for hidden input, then re-prompt for confirmation and compare.
- Use `Hash::make()` to hash; never set `password` to a plain string.
- Use Laravel 13's invokable command style if the rest of the codebase uses it; otherwise standard `Command` subclass.
- The "single user invariant" check on `user:create` is `User::count() > 0`. If somehow more than one user exists in prod, the command should refuse rather than silently create a third — print a clear error.

## Out of scope

- A web UI for user management (Settings page handles password change for the logged-in user via Fortify, see phase 50).
- Multi-user support of any kind.
