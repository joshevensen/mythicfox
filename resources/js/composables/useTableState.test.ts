import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    deserializeTableQuery,
    serializeTableQuery,
    useTableState,
} from '@/composables/useTableState';

const navigate = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: (...args: unknown[]) => navigate(...args),
    },
    usePage: () => pageStub,
}));

const pageStub = { url: '/orders' };

const lastNavigation = (): {
    endpoint: string;
    query: Record<string, string>;
    options: Record<string, unknown>;
} => {
    const call = navigate.mock.calls.at(-1);

    if (!call) {
        throw new Error('No navigation recorded.');
    }

    return {
        endpoint: call[0] as string,
        query: call[1] as Record<string, string>,
        options: call[2] as Record<string, unknown>,
    };
};

const setPageUrl = (url: string): void => {
    pageStub.url = url;
};

beforeEach(() => {
    navigate.mockClear();
    setPageUrl('/orders');
});

describe('serializeTableQuery / deserializeTableQuery — round-trip contract', () => {
    it('round-trips a complex state through URL serialization and back', () => {
        const original = {
            page: '2',
            per_page: '50',
            sort: 'order_date',
            dir: 'desc',
            status: ['Cancelled', 'Completed - Paid'],
            search: 'boltyn',
            has_override: true,
        };

        const query = serializeTableQuery(original);
        const parsed = deserializeTableQuery(
            query,
            [
                'page',
                'per_page',
                'sort',
                'dir',
                'status',
                'search',
                'has_override',
            ],
            ['status'],
        );

        expect(parsed.page).toBe('2');
        expect(parsed.per_page).toBe('50');
        expect(parsed.sort).toBe('order_date');
        expect(parsed.dir).toBe('desc');
        expect(parsed.status).toEqual(['Cancelled', 'Completed - Paid']);
        expect(parsed.search).toBe('boltyn');
        expect(parsed.has_override).toBe(true);
    });

    it('omits empty values from the URL', () => {
        const query = serializeTableQuery({
            status: '',
            page: null,
            search: undefined,
            has_override: false,
            empty_array: [],
        });

        expect(query).toBe('');
    });

    it('encodes booleans as `1` (true) or omits them (false)', () => {
        expect(serializeTableQuery({ flag: true })).toBe('flag=1');
        expect(serializeTableQuery({ flag: false })).toBe('');
    });

    it('comma-joins multi-value filters', () => {
        const query = serializeTableQuery({ status: ['A', 'B', 'C'] });

        expect(query).toBe('status=A%2CB%2CC');
        expect(decodeURIComponent(query)).toBe('status=A,B,C');
    });
});

describe('useTableState — single-value removal from a multi-value filter', () => {
    it('keeps the rest of the comma list when one entry is removed', () => {
        setPageUrl('/orders?status=A%2CB%2CC');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.removeFilter('status', 'B');

        const { query } = lastNavigation();
        expect(query.status).toBe('A,C');
    });

    it('drops the key entirely when removing the last remaining value', () => {
        setPageUrl('/orders?status=A');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.removeFilter('status', 'A');

        const { query } = lastNavigation();
        expect(query.status).toBeUndefined();
    });

    it('drops the entire key when called without a value', () => {
        setPageUrl('/orders?status=A%2CB');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.removeFilter('status');

        const { query } = lastNavigation();
        expect(query.status).toBeUndefined();
    });
});

describe('useTableState — clearFilters preserves non-table query params', () => {
    it('preserves dashboard quick-action shortcut params (e.g. import=1)', () => {
        setPageUrl('/orders?status=Cancelled&import=1&page=3');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.clearFilters();

        const { query } = lastNavigation();
        expect(query.status).toBeUndefined();
        expect(query.import).toBe('1');
        // page is dropped on every filter mutation per the doc.
        expect(query.page).toBeUndefined();
    });

    it('preserves non-filter params even when only one filter is removed', () => {
        setPageUrl('/orders?status=A%2CB&import=1&export=1');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.removeFilter('status', 'A');

        const { query } = lastNavigation();
        expect(query.status).toBe('B');
        expect(query.import).toBe('1');
        expect(query.export).toBe('1');
    });

    it('preserves non-filter params on setFilter writes', () => {
        setPageUrl('/orders?import=1');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.setFilter('status', ['Cancelled']);

        const { query } = lastNavigation();
        expect(query.status).toBe('Cancelled');
        expect(query.import).toBe('1');
    });
});

describe('useTableState — pagination + sort writers', () => {
    it('setPage drops the param when set to 1', () => {
        setPageUrl('/orders?page=3');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: [],
        });

        ts.setPage(1);

        expect(lastNavigation().query.page).toBeUndefined();
    });

    it('setPage stores the value when greater than 1', () => {
        setPageUrl('/orders');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: [],
        });

        ts.setPage(5);

        expect(lastNavigation().query.page).toBe('5');
    });

    it('setPerPage drops the param when matching the default', () => {
        setPageUrl('/orders?per_page=100');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: [],
            defaultPerPage: 50,
        });

        ts.setPerPage(50);

        expect(lastNavigation().query.per_page).toBeUndefined();
    });

    it('setSort writes both sort and dir', () => {
        setPageUrl('/orders');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: [],
        });

        ts.setSort({ field: 'order_date', dir: 'desc' });

        const { query } = lastNavigation();
        expect(query.sort).toBe('order_date');
        expect(query.dir).toBe('desc');
    });

    it('setSort(null) clears both sort and dir', () => {
        setPageUrl('/orders?sort=order_date&dir=desc');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: [],
        });

        ts.setSort(null);

        const { query } = lastNavigation();
        expect(query.sort).toBeUndefined();
        expect(query.dir).toBeUndefined();
    });

    it('exposes sort default when no URL state is present', () => {
        setPageUrl('/orders');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: [],
            defaultSort: { field: 'order_date', dir: 'desc' },
        });

        expect(ts.sort.value).toEqual({ field: 'order_date', dir: 'desc' });
    });

    it('URL sort state overrides the default', () => {
        setPageUrl('/orders?sort=buyer_name&dir=asc');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: [],
            defaultSort: { field: 'order_date', dir: 'desc' },
        });

        expect(ts.sort.value).toEqual({ field: 'buyer_name', dir: 'asc' });
    });
});

describe('useTableState — filtersComplete predicate', () => {
    it('returns true by default when no predicate is supplied', () => {
        setPageUrl('/inventory');

        const ts = useTableState({
            endpoint: '/inventory',
            filterKeys: ['product', 'sets', 'conditions'],
        });

        expect(ts.filtersComplete.value).toBe(true);
    });

    it('runs the supplied predicate against the URL filter state', () => {
        setPageUrl('/inventory?product=1&sets=2&conditions=Near%20Mint');

        const ts = useTableState({
            endpoint: '/inventory',
            filterKeys: ['product', 'sets', 'conditions'],
            filtersComplete: (raw) =>
                Boolean(raw.product) &&
                Boolean(raw.sets) &&
                Boolean(raw.conditions),
        });

        expect(ts.filtersComplete.value).toBe(true);
    });

    it('returns false when the predicate fails', () => {
        setPageUrl('/inventory?product=1');

        const ts = useTableState({
            endpoint: '/inventory',
            filterKeys: ['product', 'sets', 'conditions'],
            filtersComplete: (raw) =>
                Boolean(raw.product) &&
                Boolean(raw.sets) &&
                Boolean(raw.conditions),
        });

        expect(ts.filtersComplete.value).toBe(false);
    });
});

describe('useTableState — Inertia options on every navigation', () => {
    it('always uses replace: true and preserveState: true', () => {
        setPageUrl('/orders');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.setFilter('status', 'Cancelled');

        const { options } = lastNavigation();
        expect(options.replace).toBe(true);
        expect(options.preserveState).toBe(true);
        expect(options.preserveScroll).toBe(true);
    });

    it('passes inertiaOnly through when configured', () => {
        setPageUrl('/orders');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
            inertiaOnly: ['orders'],
        });

        ts.setPage(2);

        expect(lastNavigation().options.only).toEqual(['orders']);
    });

    it('omits inertiaOnly when not configured', () => {
        setPageUrl('/orders');

        const ts = useTableState({
            endpoint: '/orders',
            filterKeys: ['status'],
        });

        ts.setPage(2);

        expect(lastNavigation().options.only).toBeUndefined();
    });
});
