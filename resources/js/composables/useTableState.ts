import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Shared URL-driven state for the Orders, Catalog, and Inventory tables.
 *
 * Single source of truth for the table-owned query keys (`page`, `per_page`,
 * `sort`, `dir`, plus per-page `filterKeys`). Reads the current Inertia page
 * URL on every access so navigation, refresh, and back/forward all
 * resolve to the same state without extra wiring. Writes route through
 * Inertia's `router.get(...)` with `replace: true` so filter tweaks don't
 * flood browser history.
 *
 * Per docs/ux/ux-patterns.md#url-driven-state:
 *   - Multi-value filters serialize comma-separated.
 *   - `dir` is `asc` or `desc`.
 *   - Empty values are omitted from the URL.
 *
 * Non-table query params (e.g. dashboard quick-action shortcuts like
 * `?import=1` / `?export=1`) are preserved across every state change —
 * `writeUrl` reads the existing URL and only mutates the requested keys.
 */

export type TableFilterValue = string | string[] | boolean | null | undefined;

export type SortDirection = 'asc' | 'desc';

export type SortState = {
    field: string;
    dir: SortDirection;
} | null;

export type UseTableStateOptions = {
    /** Inertia route URL (e.g. `inventoryIndex().url`). */
    endpoint: string;
    /** Query keys this page treats as filters. */
    filterKeys: readonly string[];
    /** Default page size when none is in the URL. */
    defaultPerPage?: number;
    /** Default sort applied when none is in the URL. */
    defaultSort?: { field: string; dir: SortDirection };
    /**
     * Optional client-side mirror of the controller's "filters complete"
     * predicate. Used by the Inventory page's required-filter contract.
     */
    filtersComplete?: (raw: Readonly<Record<string, string>>) => boolean;
    /**
     * Inertia partial-reload prop names to limit each navigation to. Mirrors
     * what `MfTable` used to pass via its own `inertiaOnly` prop — keeps
     * non-table page props (e.g. modal state, in-flight job flags) from
     * being re-fetched on every filter/page change.
     */
    inertiaOnly?: readonly string[];
};

const PAGINATION_KEYS = ['page', 'per_page', 'sort', 'dir'] as const;

export function useTableState(options: UseTableStateOptions) {
    const inertiaPage = usePage();

    const currentUrl = (): URL => {
        const href =
            typeof window === 'undefined'
                ? `http://localhost${inertiaPage.url}`
                : new URL(inertiaPage.url, window.location.origin).toString();

        return new URL(href);
    };

    const filters: ComputedRef<Record<string, string>> = computed(() => {
        const url = currentUrl();
        const out: Record<string, string> = {};

        for (const key of options.filterKeys) {
            const value = url.searchParams.get(key);

            if (value !== null && value !== '') {
                out[key] = value;
            }
        }

        return out;
    });

    const page: ComputedRef<number> = computed(() => {
        const raw = currentUrl().searchParams.get('page');
        const parsed = raw === null ? 1 : Number(raw);

        return Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
    });

    const perPage: ComputedRef<number> = computed(() => {
        const raw = currentUrl().searchParams.get('per_page');
        const parsed = raw === null ? null : Number(raw);

        if (parsed !== null && Number.isFinite(parsed) && parsed > 0) {
            return parsed;
        }

        return options.defaultPerPage ?? 50;
    });

    const sort: ComputedRef<SortState> = computed(() => {
        const url = currentUrl();
        const field = url.searchParams.get('sort');
        const dirRaw = url.searchParams.get('dir');

        if (field && (dirRaw === 'asc' || dirRaw === 'desc')) {
            return { field, dir: dirRaw };
        }

        if (options.defaultSort) {
            return {
                field: options.defaultSort.field,
                dir: options.defaultSort.dir,
            };
        }

        return null;
    });

    const hasActiveFilters: ComputedRef<boolean> = computed(
        () => Object.keys(filters.value).length > 0,
    );

    const filtersComplete: ComputedRef<boolean> = computed(() =>
        options.filtersComplete ? options.filtersComplete(filters.value) : true,
    );

    /**
     * Apply `mutate` to a copy of the current URL's query params and navigate
     * via Inertia. Non-table params (anything outside `filterKeys` and the
     * pagination/sort keys) survive every call.
     */
    const writeUrl = (mutate: (params: URLSearchParams) => void): void => {
        const url = currentUrl();
        const params = url.searchParams;

        mutate(params);

        router.get(options.endpoint, Object.fromEntries(params.entries()), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: options.inertiaOnly?.length
                ? [...options.inertiaOnly]
                : undefined,
        });
    };

    const setFilter = (key: string, value: TableFilterValue): void => {
        writeUrl((params) => {
            applyFilterMutation(params, key, value);
            // Filter changes always reset pagination — the prior page may
            // not exist under the new filter set.
            params.delete('page');
        });
    };

    /**
     * If `value` is given and the existing param is a comma-separated list,
     * drops that one entry and keeps the rest. Otherwise drops the key.
     */
    const removeFilter = (key: string, value?: string): void => {
        writeUrl((params) => {
            if (value === undefined) {
                params.delete(key);
            } else {
                const existing = params.get(key);

                if (existing !== null) {
                    const remaining = existing
                        .split(',')
                        .map((v) => v.trim())
                        .filter((v) => v !== '' && v !== value);

                    if (remaining.length === 0) {
                        params.delete(key);
                    } else {
                        params.set(key, remaining.join(','));
                    }
                }
            }

            params.delete('page');
        });
    };

    const clearFilters = (): void => {
        writeUrl((params) => {
            for (const key of options.filterKeys) {
                params.delete(key);
            }

            params.delete('page');
        });
    };

    const setPage = (next: number): void => {
        writeUrl((params) => {
            if (next <= 1) {
                params.delete('page');
            } else {
                params.set('page', String(next));
            }
        });
    };

    const setPerPage = (next: number): void => {
        writeUrl((params) => {
            if (next === (options.defaultPerPage ?? 50)) {
                params.delete('per_page');
            } else {
                params.set('per_page', String(next));
            }

            // Resizing pages always returns to page 1; the prior page index
            // doesn't translate.
            params.delete('page');
        });
    };

    const setSort = (next: SortState): void => {
        writeUrl((params) => {
            if (next === null) {
                params.delete('sort');
                params.delete('dir');
            } else {
                params.set('sort', next.field);
                params.set('dir', next.dir);
            }

            params.delete('page');
        });
    };

    return {
        // reactive state
        filters,
        page,
        perPage,
        sort,
        hasActiveFilters,
        filtersComplete,
        // writers
        setFilter,
        removeFilter,
        clearFilters,
        setPage,
        setPerPage,
        setSort,
    };
}

/**
 * Apply a single-key filter mutation to a URLSearchParams object. Pulled out
 * so `setFilter` can drop `page` afterwards in one place.
 */
function applyFilterMutation(
    params: URLSearchParams,
    key: string,
    value: TableFilterValue,
): void {
    if (value === null || value === undefined || value === '') {
        params.delete(key);

        return;
    }

    if (typeof value === 'boolean') {
        if (value) {
            params.set(key, '1');
        } else {
            params.delete(key);
        }

        return;
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            params.delete(key);
        } else {
            params.set(key, value.join(','));
        }

        return;
    }

    params.set(key, String(value));
}

/**
 * Pure URL-query serializer for table state. Exposed so callers (and unit
 * tests) can build the same query shape the composable produces without
 * firing an Inertia visit. Empty/false/null/undefined values are omitted;
 * arrays are comma-joined.
 */
export function serializeTableQuery(
    state: Record<string, TableFilterValue>,
): string {
    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(state)) {
        applyFilterMutation(params, key, value);
    }

    return params.toString();
}

/**
 * Pure round-trip helper: parse a URL query string back into a typed state
 * object given a list of expected keys. Multi-value filters split on commas;
 * boolean filters (encoded as `1`) are coerced back to `true`. Keys absent
 * from the URL are absent from the result.
 */
export function deserializeTableQuery(
    query: string,
    expectedKeys: readonly string[],
    multiValueKeys: readonly string[] = [],
): Record<string, string | string[] | boolean> {
    const params = new URLSearchParams(query);
    const out: Record<string, string | string[] | boolean> = {};

    for (const key of expectedKeys) {
        const raw = params.get(key);

        if (raw === null || raw === '') {
            continue;
        }

        if (multiValueKeys.includes(key)) {
            out[key] = raw
                .split(',')
                .map((v) => v.trim())
                .filter((v) => v !== '');
            continue;
        }

        if (raw === '1') {
            // Heuristic: a bare `1` is treated as the boolean encoding.
            // Callers that need numeric `1` should opt out by listing the
            // key in `multiValueKeys` (or just read directly from the URL).
            out[key] = true;
            continue;
        }

        out[key] = raw;
    }

    return out;
}

export const TABLE_PAGINATION_KEYS = PAGINATION_KEYS;
