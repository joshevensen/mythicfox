import { describe, expect, it } from 'vitest';
import { abbreviateCondition } from '@/lib/conditionAbbreviation';

describe('abbreviateCondition', () => {
    it.each([
        ['Near Mint', 'NM'],
        ['Lightly Played', 'LP'],
        ['Moderately Played', 'MP'],
        ['Heavily Played', 'HP'],
        ['Damaged', 'D'],
    ])('abbreviates %s to %s', (condition, expected) => {
        expect(abbreviateCondition(condition)).toBe(expected);
    });

    it.each([
        ['Near Mint Foil', 'NM Foil'],
        ['Lightly Played Foil', 'LP Foil'],
        ['Moderately Played Foil', 'MP Foil'],
        ['Heavily Played Foil', 'HP Foil'],
        ['Damaged Foil', 'D Foil'],
    ])('preserves foil suffix: %s → %s', (condition, expected) => {
        expect(abbreviateCondition(condition)).toBe(expected);
    });

    it('returns short unknown conditions as-is', () => {
        expect(abbreviateCondition('NM')).toBe('NM');
    });

    it('truncates long unknown conditions to 3 chars', () => {
        expect(abbreviateCondition('Unknown')).toBe('Unk');
    });
});
