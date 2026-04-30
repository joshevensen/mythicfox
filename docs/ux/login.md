# Login

The single auth surface in the app. Email + password → admin dashboard. No registration, no password reset, no 2FA — those are disabled per [saas-design.md §Auth](../saas-design.md). Fortify provides the underlying machinery; this page is the only auth-related view.

**Route**: `/login`
**Access**: public (the only unauthenticated route besides the public homepage)
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [saas-design.md](../saas-design.md)

---

## Purpose

Sign the operator in. That's it.

---

## Layout

Centered card on a neutral background. Same brand styling as the public homepage so the auth surface feels like part of the same site, not a generic Laravel login.

```
┌──────────────────────────────────────┐
│                                      │
│                                      │
│         [Mythic Fox logo]            │
│                                      │
│           Sign in                    │
│                                      │
│   ┌──────────────────────────────┐   │
│   │  Email                       │   │
│   ├──────────────────────────────┤   │
│   │                              │   │
│   └──────────────────────────────┘   │
│                                      │
│   ┌──────────────────────────────┐   │
│   │  Password                    │   │
│   ├──────────────────────────────┤   │
│   │                              │   │
│   └──────────────────────────────┘   │
│                                      │
│   [           Sign in            ]   │
│                                      │
└──────────────────────────────────────┘
```

### Components

| Element | Notes |
|---|---|
| Logo | Same Mythic Fox brand asset used in admin top nav and public homepage |
| Title | "Sign in" |
| Email input | `MfFormField` wrapping a standard text input. `type="email"`, autocomplete=`email`, autofocus on page load |
| Password input | `MfFormField` wrapping `type="password"`, autocomplete=`current-password` |
| Submit button | Full-width primary button labeled "Sign in" |

**No "forgot password" link**, no "register" link, no 2FA prompt — those features are intentionally absent. If credentials are forgotten, recovery is via the `php artisan user:reset-password` command on the droplet (per [saas-design.md](../saas-design.md)).

---

## Interactions

### Submit

POSTs to Fortify's login endpoint. Success → redirect to `/dashboard`. Failure → render the same page with an inline error above the form.

| Failure | Message |
|---|---|
| Invalid email or password | "Email or password incorrect." |
| Rate-limited (5 attempts/min, Fortify default) | "Too many attempts — try again in {N} seconds." |
| Server error | "Something went wrong. Try again." |

The error message for invalid credentials is generic on purpose — it does not distinguish between "no such email" and "wrong password," which would leak whether an account exists.

### Already signed in

If a logged-in user navigates to `/login`, redirect to `/dashboard` immediately.

---

## Data

Reads / writes:

- Standard Fortify auth flow; no app-specific writes from this page.

---

## States

| State | Display |
|---|---|
| Default | Empty form, email field focused. |
| Submitting | Submit button shows spinner, fields disabled. |
| Invalid credentials | Inline error above the form; password field cleared, email retained. |
| Rate-limited | Inline error with countdown; submit button disabled until countdown expires. |
