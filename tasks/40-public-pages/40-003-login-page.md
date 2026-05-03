---
id: "40-003"
title: "Login page at `/login` — email + password"
status: complete
phase: "40-public-pages"
size: M
depends_on: ["40-001", "phase:00-foundation", "phase:30-components"]
references:
  - docs/ux/login.md#layout
  - docs/ux/login.md#components
  - docs/ux/login.md#interactions
  - docs/ux/login.md#states
  - docs/saas-design.md#auth--users
---

## Goal

The single auth surface. Centered card with the Mythic Fox logo, "Sign in" title, email and password fields, and a full-width submit button. POSTs to Fortify's login endpoint; success redirects to `/dashboard`, failure renders an inline generic error. No registration link, no "forgot password" link, no Remember-me, no 2FA — those features were stripped in `00-002`.

## Acceptance criteria

- [x] Route `GET /login` renders an Inertia page using `PublicLayout` from `40-001`. Page title is `"Sign in — Mythic Fox Games"`.
- [x] The page renders: Mythic Fox logo, "Sign in" heading, an email input (`type="email"`, `autocomplete="email"`, autofocused on mount), a password input (`type="password"`, `autocomplete="current-password"`), and a full-width primary submit button labeled "Sign in".
- [x] Email and password inputs are wrapped in `MfFormField` from phase 30 so inline errors render correctly with Inertia's `errors` prop.
- [x] No "Forgot password?" link, no "Register" link, no Remember-me checkbox, no 2FA challenge route — page contains exactly the elements above plus the layout chrome.
- [x] Submit POSTs to Fortify's login endpoint with `email` and `password` only (no `remember` field). On success, redirect lands on `/dashboard`.
- [x] Failure handling per `login.md §Interactions`:
  - Invalid credentials → inline error above the form: `"Email or password incorrect."` (generic — never distinguish "no such email" from "wrong password"). Password field cleared, email retained.
  - Rate-limited (Fortify default 5/min) → inline error with countdown; submit disabled until countdown expires.
  - Server error → inline error: `"Something went wrong. Try again."`
- [x] Submitting state: submit button shows a spinner, both inputs are disabled.
- [x] Already-authenticated visitor hitting `GET /login` is redirected to `/dashboard` server-side via middleware (do NOT rely on a client guard alone).
- [x] Pest feature test `tests/Feature/Public/LoginTest.php` covers:
  - Anonymous request to `/login` returns 200 and renders the expected fields.
  - Authenticated request to `/login` redirects to `/dashboard`.
  - Valid credentials log in and redirect to `/dashboard`.
  - Invalid credentials return a generic error message and do not log in.
  - The page does NOT contain the strings "Forgot", "Register", "Remember", or "Two-factor" / "2FA".
- [x] `composer test` passes.

## Implementation notes

- Fortify's login route is configured by `00-002`; this task wires the Inertia view, not the auth machinery.
- Wayfinder generates a typed route for `/login` and the Fortify POST target — use them; no hardcoded URLs.
- Session lifetime is governed by `SESSION_LIFETIME` in `.env`. Without a Remember-me checkbox, every login uses the standard session lifetime — there is no long-lived "remember" cookie path.
- Password reset is via `php artisan user:reset-password` on the droplet (per `00-003`); do NOT add a forgot-password link, even hidden behind a "v2" feature flag.
- Use Wayfinder typed routes everywhere; activate `inertia-vue-development` and `wayfinder-development` skills per `AGENTS.md`.
- The login page must visually match the public homepage chrome — logo, brand colors, dark-mode support — because they share `PublicLayout`.

## Out of scope

- Forgot-password / reset-password UI (intentionally absent — recovery is the artisan command from `00-003`).
- Registration form (intentionally absent — single user, created via `00-003` artisan command).
- Remember-me / persistent sessions (intentionally absent — standard session lifetime only).
- Two-factor authentication challenge (stripped in `00-002`).
- Logout flow / user menu (admin-side concern, handled by `MfTopNav` and `50-001`).
- Public layout chrome (that's `40-001`).
- Public footer (that's `40-004`).
