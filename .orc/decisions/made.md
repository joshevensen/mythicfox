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
- New migration (add/drop columns); never edit the original create migration — #70 — added `products.display_name` in its own migration

## Sorting a paginated query by a nullable computed value with nulls last
- `orderByRaw("{expr} IS NULL")` then `orderByRaw("{expr} {dir}")`, repeating the expression instead of its alias — #66 — portable across PostgreSQL/MySQL/SQLite; PostgreSQL rejects aliases inside ORDER BY expressions

## Bulk-upserting rows whose columns arrive in separate CSV rows or chunks
- Buffer per column group and run one `upsert` per group, each with only that group's update columns — #66 — a single combined upsert nulls other groups' columns across chunk boundaries and depends on row order

## Showing a friendlier label for a record whose name is an import/sync match key
- Separate nullable `display_name` column plus a model `display_label` accessor (`display_name ?? name`) sent in payloads — #70 — editing `name` would break import matching and create duplicates

## Naming a route that is also a prefix of existing nested route names
- Suffix with `.index` (e.g. `settings.products.index`) — #70 — avoids a Wayfinder leaf-vs-namespace clash with `settings.products.*`; matches `catalog.index`
- Use `.show`/`.index` leaves under namespace-only segments (`catalog.products.show`, `catalog.products.sets.show`) — #71 — no segment is both a route and a namespace

## Adding a settings area
- Its own `settings/*` Inertia page listed in the settings sidebar (`layouts/settings/Layout.vue`) — #70 — `resolveLayout` only gives the settings shell to `settings/*` pages

## Parsing sort/direction/per-page query params shared by several table controllers
- Small final class with a static `fromRequest()` (`App\Catalog\CatalogTableParams`), spread into the query filters — #71 — removes copied constants/helpers; sort columns stay validated by each query class

## Top-nav item that opens a submenu instead of linking
- Optional `dropdown` flag on the `Section` entry plus a PrimeVue Popover (drawer lists sub-items indented) — #71 — keeps `SECTIONS` the single source of order/labels; no label string checks

## Scoping a nested route model (child must belong to parent in the URL)
- `->scopeBindings()` on the route with `whereNumber` on the IDs — #71 — mismatched parent/child returns 404 with no controller code
- `->scopeBindings()` with `whereNumber`, resolving the child through a HasManyThrough (`Product::cards()`) — #72 — Laravel supports through-relations for scoped bindings, so no controller ownership check

## Reusing a Vue component that lives under pages/ from a second page
- `git mv` it to `resources/js/components/{area}/` with a descriptive name, keeping its root `data-test` — #72 — pages/ is for Inertia pages; shared pieces live in components/

## Calling a JSON endpoint from a Vue component
- Plain `fetch` with a Wayfinder URL and `AbortController` to drop stale responses — #73 — matches OrderItemsPanel/FileHistorySection; direct cancellation and easy to stub, over Inertia `useHttp`

## Testing stateful/timing frontend logic (debounce, aborts, keyboard highlight)
- Extract it into a composable with injected dependencies and cover it with vitest; markup stays on Pest source assertions — #73 — source-text checks can't verify timing or response order

## Rate-limiting an expensive authenticated JSON endpoint
- `->middleware('throttle:60,1')` on the route plus a 429 test — #73 — client debounce isn't a server control; unindexed ILIKE scans per call
