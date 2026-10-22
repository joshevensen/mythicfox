## Default layout for an Inertia page name no layout rule matches
- Admin shell (`AdminLayout`) via `resolveLayout()` — #62 — a renamed or new app page can't silently get a different shell; guest pages live under `public/` or `auth/`

## Testing which layout a page name resolves to
- Pure function returning a layout key, unit-tested with vitest — #62 — avoids importing `.vue` files and brittle source-text assertions on `app.ts`

## Rendering server-flashed toasts
- `useFlashToast()` composable in the admin layout, rendering through PrimeVue `MfToast` — #62 — the vue-sonner `Toaster` was only mounted by the removed starter-kit layouts
