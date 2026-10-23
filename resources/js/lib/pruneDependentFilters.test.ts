import { describe, expect, it } from 'vitest';
import type { DependentFilter } from '@/lib/pruneDependentFilters';
import { pruneDependentFilters } from '@/lib/pruneDependentFilters';

const dependents: DependentFilter[] = [
    {
        key: 'sets',
        optionsByProduct: {
            '1': [
                { value: '10', label: 'Set A' },
                { value: '11', label: 'Set B' },
            ],
            '2': [{ value: '20', label: 'Set C' }],
        },
    },
    {
        key: 'rarities',
        optionsByProduct: {
            '1': [
                { value: 'Rare', label: 'Rare' },
                { value: 'Super Rare', label: 'Super Rare' },
            ],
            '2': [{ value: 'Common', label: 'Common' }],
        },
    },
];

describe('pruneDependentFilters', () => {
    it('returns null when nothing is selected', () => {
        expect(pruneDependentFilters('?product=1', '1', dependents)).toBeNull();
    });

    it('returns null when every selected value is valid', () => {
        expect(
            pruneDependentFilters(
                '?product=1&sets=10,11&rarities=Rare',
                '1',
                dependents,
            ),
        ).toBeNull();
    });

    it('drops invalid sets, keeps valid ones, and removes page', () => {
        const result = pruneDependentFilters(
            '?product=2&sets=10,20&page=3',
            '2',
            dependents,
        );

        expect(result).toEqual({
            query: { product: '2', sets: '20' },
            dropped: [{ key: 'sets', count: 1 }],
        });
    });

    it('prunes sets and rarities in one result, in input order', () => {
        const result = pruneDependentFilters(
            '?product=2&sets=10,11&rarities=Rare,Common',
            '2',
            dependents,
        );

        expect(result).toEqual({
            query: { product: '2', rarities: 'Common' },
            dropped: [
                { key: 'sets', count: 2 },
                { key: 'rarities', count: 1 },
            ],
        });
    });

    it('deletes both params when the product is cleared', () => {
        const result = pruneDependentFilters(
            '?sets=10&rarities=Rare',
            '',
            dependents,
        );

        expect(result?.query).toEqual({});
        expect(result?.dropped).toEqual([
            { key: 'sets', count: 1 },
            { key: 'rarities', count: 1 },
        ]);
    });

    it('preserves unrelated params', () => {
        const result = pruneDependentFilters(
            '?product=2&sets=10&sort=name&dir=desc&per_page=100',
            '2',
            dependents,
        );

        expect(result?.query).toEqual({
            product: '2',
            sort: 'name',
            dir: 'desc',
            per_page: '100',
        });
    });

    it('keeps rarity values containing spaces', () => {
        const result = pruneDependentFilters(
            '?product=1&rarities=Super%20Rare,Common',
            '1',
            dependents,
        );

        expect(result?.query).toEqual({
            product: '1',
            rarities: 'Super Rare',
        });
    });
});
