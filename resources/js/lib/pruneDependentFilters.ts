import type { FilterOption } from '@/components/MfFilter.types';

export type DependentFilter = {
    /** URL query key holding a CSV of selected values, e.g. 'sets' or 'rarities'. */
    key: string;
    /** Allowed options per product id (string). */
    optionsByProduct: Record<string, FilterOption[]>;
};

export type DroppedFilter = { key: string; count: number };

export type PruneResult = {
    /** Query params after pruning, with `page` removed. Ready for router.get(). */
    query: Record<string, string>;
    /** One entry per dependent key that lost at least one value, in input order. */
    dropped: DroppedFilter[];
};

/**
 * Drops dependent filter values that don't belong to the selected product.
 * Returns null when nothing needs pruning.
 */
export function pruneDependentFilters(
    search: string,
    productId: string,
    dependents: DependentFilter[],
): PruneResult | null {
    const params = new URLSearchParams(search);
    const dropped: DroppedFilter[] = [];

    for (const dependent of dependents) {
        const selected = (params.get(dependent.key) ?? '')
            .split(',')
            .map((value) => value.trim())
            .filter((value) => value.length > 0);

        if (selected.length === 0) {
            continue;
        }

        const allowed = new Set(
            (dependent.optionsByProduct[productId] ?? []).map((o) => o.value),
        );
        const kept = selected.filter((value) => allowed.has(value));
        const count = selected.length - kept.length;

        if (count === 0) {
            continue;
        }

        dropped.push({ key: dependent.key, count });

        if (kept.length > 0) {
            params.set(dependent.key, kept.join(','));
        } else {
            params.delete(dependent.key);
        }
    }

    if (dropped.length === 0) {
        return null;
    }

    params.delete('page');

    return { query: Object.fromEntries(params.entries()), dropped };
}
