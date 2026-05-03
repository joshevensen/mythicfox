import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Shared URL-driven state for the Orders, Catalog, and Inventory tables.
 *
 * Consolidates the page-level helpers (`currentUrl()`, `hasActiveFilters`,
 * `clearAllFilters`, ad-hoc `router.get` calls) the three list pages each had
 * inline. The composable is a thin wrapper around the URL: it reads the
 * current Inertia page URL, lets pages mutate the table-owned query keys
 * (`page`, `per_page`, `sort`, `dir`, plus the per-page `filterKeys`), and
 * preserves any other params (e.g. dashboard quick-action shortcuts like
 * `?import=1`).
 *
 * Pagination + sort are still owned by `MfTable` internally; the filter UI is
 * still owned by `MfFilterPanel` internally. This composable covers the page-
 * level concerns those two don't: composing `clearFilters`, exposing
 * `filtersComplete` for the Inventory required-filter contract, and turning
 * non-form click handlers (the "N overrides active" toggle) into one-line
 * URL mutations.
 *
 * Per docs/ux/ux-patterns.md#url-driven-state:
 *   - Multi-value filters serialize comma-separated.
 *   - `dir` is `asc` or `desc`.
 *   - Empty values are omitted from the URL.
 *   - State changes use `replace: true` so filter tweaks don't flood
 *     browser history.
 */

export type TableFilterValue = string | string[] | boolean | null | undefined;

export type UseTableStateOptions = {
    /** Inertia route URL (e.g. `inventoryIndex().url`). */
    endpoint: string;
    /** Query keys this page treats as filters. */
    filterKeys: readonly string[];
    /**
     * Optional client-side mirror of the controller's "filters complete"
     * predicate. Used by the Inventory page's required-filter contract.
     */
    filtersComplete?: (raw: Readonly<Record<string, string>>) => boolean;
};

export function useTableState(options: UseTableStateOptions) {
    const page = usePage();

    const currentUrl = (): URL => {
        const href =
            typeof window === 'undefined'
                ? `http://localhost${page.url}`
                : new URL(page.url, window.location.origin).toString();

        return new URL(href);
    };

    const filters: ComputedRef<Record<string, string>> = computed(() => {
        // Re-read on every page.url change so navigation back/forward gets
        // picked up reactively. The dependency on page.url is established by
        // calling currentUrl() inside computed().
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

    const hasActiveFilters: ComputedRef<boolean> = computed(
        () => Object.keys(filters.value).length > 0,
    );

    const filtersComplete: ComputedRef<boolean> = computed(() =>
        options.filtersComplete ? options.filtersComplete(filters.value) : true,
    );

    /**
     * Build a preserves-non-table-params query object from the current URL,
     * apply `mutate`, and navigate via Inertia. Always drops `page` so the
     * caller doesn't have to remember to reset pagination on filter changes.
     */
    const writeUrl = (mutate: (params: URLSearchParams) => void): void => {
        const url = currentUrl();
        const params = url.searchParams;

        mutate(params);
        params.delete('page');

        router.get(options.endpoint, Object.fromEntries(params.entries()), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const setFilter = (key: string, value: TableFilterValue): void => {
        writeUrl((params) => {
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
        });
    };

    /**
     * If `value` is given and the existing param is a comma-separated list,
     * drops that one entry and keeps the rest. Otherwise drops the key.
     */
    const removeFilter = (key: string, value?: string): void => {
        if (value === undefined) {
            writeUrl((params) => params.delete(key));

            return;
        }

        writeUrl((params) => {
            const existing = params.get(key);

            if (existing === null) {
                return;
            }

            const remaining = existing
                .split(',')
                .map((v) => v.trim())
                .filter((v) => v !== '' && v !== value);

            if (remaining.length === 0) {
                params.delete(key);
            } else {
                params.set(key, remaining.join(','));
            }
        });
    };

    const clearFilters = (): void => {
        writeUrl((params) => {
            for (const key of options.filterKeys) {
                params.delete(key);
            }
        });
    };

    return {
        filters,
        hasActiveFilters,
        filtersComplete,
        setFilter,
        removeFilter,
        clearFilters,
    };
}

/**
 * Pure URL-string serializer for the table state. Exposed so feature tests
 * (and callers that need a URL without firing an Inertia visit) can build
 * the same shape the composable produces. Empty/false/null/undefined values
 * are omitted; arrays are comma-joined.
 */
export function serializeTableQuery(
    state: Record<string, TableFilterValue>,
): string {
    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(state)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        if (typeof value === 'boolean') {
            if (value) {
                params.set(key, '1');
            }

            continue;
        }

        if (Array.isArray(value)) {
            if (value.length === 0) {
                continue;
            }

            params.set(key, value.join(','));

            continue;
        }

        params.set(key, String(value));
    }

    return params.toString();
}
