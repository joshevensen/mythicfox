## Default layout for an Inertia page name no layout rule matches
- Admin shell (`AdminLayout`) via `resolveLayout()` — #62 — a renamed or new app page can't silently get a different shell; guest pages live under `public/` or `auth/`

## Testing which layout a page name resolves to
- Pure function returning a layout key, unit-tested with vitest — #62 — avoids importing `.vue` files and brittle source-text assertions on `app.ts`

## Rendering server-flashed toasts
- `useFlashToast()` composable in the admin layout, rendering through PrimeVue `MfToast` — #62 — the vue-sonner `Toaster` was only mounted by the removed starter-kit layouts

## Adding a capability to the shared MfFilterPanel that only one page needs
- Optional, default-off `FilterDef` fields (`searchable`, `disabled`, `disabledPlaceholder`) — #65 — keeps one filter panel; other consumers unchanged

## Caching filter options derived from scanning catalog cards
- `Cache::rememberForever` in a `App\Catalog\Queries` class, with a static `forget()` called by every card writer (import, sync, truncate commands) — #65 — card writes are bulk upserts/truncates that bypass model events, so explicit invalidation is the only reliable hook

## Asserting PrimeVue component props in a vitest component test
- Explicit `defineComponent` stubs declaring the props under test — #65 — PrimeVue declares props on an extended base component, which `stubs: { X: true }` doesn't copy

## Changing the columns of an existing table
- New migration (add/drop columns); never edit the original create migration — #66 — deployed databases must migrate forward cleanly

## Sorting a paginated query by a nullable computed value with nulls last
- `orderByRaw("{expr} IS NULL")` then `orderByRaw("{expr} {dir}")`, repeating the expression instead of its alias — #66 — portable across PostgreSQL/MySQL/SQLite; PostgreSQL rejects aliases inside ORDER BY expressions

## Bulk-upserting rows whose columns arrive in separate CSV rows or chunks
- Buffer per column group and run one `upsert` per group, each with only that group's update columns — #66 — a single combined upsert nulls other groups' columns across chunk boundaries and depends on row order

## Covering Inertia page template changes when the page has no vitest test
- Pest Inertia prop assertions plus `file_get_contents` source assertions on the `.vue` file, with `types:check` — #66 — avoids building a component-test harness for one page
