import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useCardSearch } from '@/composables/useCardSearch';
import type { CardSearchResult } from '@/composables/useCardSearch';

const endpoint = (t: string) => '/catalog/search?q=' + encodeURIComponent(t);

const row = (id: number, name = 'Bolt'): CardSearchResult => ({
    printing_id: id,
    card_id: id,
    product_id: 1,
    name,
    product_label: 'MTG',
    set_name: 'Alpha',
    number: String(id),
    finish: 'non-foil',
});

const okResponse = (data: CardSearchResult[]) =>
    ({ ok: true, status: 200, json: async () => ({ data }) }) as Response;

describe('useCardSearch', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock.mockReset();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('does not search below the minimum length', async () => {
        const search = useCardSearch({ endpoint });

        search.onInput('a');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(search.open.value).toBe(false);

        search.onInput(' a ');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('debounces input into a single request', async () => {
        fetchMock.mockResolvedValue(okResponse([]));
        const search = useCardSearch({ endpoint });

        search.onInput('bo');
        search.onInput('bol');
        search.onInput('bolt');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toContain('q=bolt');
    });

    it('aborts and ignores stale requests', async () => {
        let resolveFirst: (value: Response) => void = () => {};

        fetchMock
            .mockImplementationOnce(
                () =>
                    new Promise<Response>((resolve) => {
                        resolveFirst = resolve;
                    }),
            )
            .mockResolvedValueOnce(okResponse([row(2, 'Second')]));
        const search = useCardSearch({ endpoint });

        search.onInput('bo');
        await vi.advanceTimersByTimeAsync(300);
        const firstSignal = fetchMock.mock.calls[0][1].signal as AbortSignal;

        search.onInput('bolt');
        await vi.advanceTimersByTimeAsync(300);

        expect(firstSignal.aborted).toBe(true);

        resolveFirst(okResponse([row(1, 'First')]));
        await vi.advanceTimersByTimeAsync(0);

        expect(search.results.value.map((r) => r.name)).toEqual(['Second']);
        expect(search.loading.value).toBe(false);
    });

    it('opens with the first result highlighted', async () => {
        fetchMock.mockResolvedValueOnce(okResponse([row(1), row(2)]));
        const search = useCardSearch({ endpoint });

        search.onInput('bolt');
        await vi.advanceTimersByTimeAsync(300);

        expect(search.open.value).toBe(true);
        expect(search.highlightedIndex.value).toBe(0);
        expect(search.highlighted.value?.printing_id).toBe(1);
        expect(search.loading.value).toBe(false);
        expect(search.searched.value).toBe(true);

        fetchMock.mockResolvedValueOnce(okResponse([]));
        search.onInput('nothing');
        await vi.advanceTimersByTimeAsync(300);

        expect(search.highlightedIndex.value).toBe(-1);
        expect(search.searched.value).toBe(true);
    });

    it('moves the highlight with wrap-around', async () => {
        const search = useCardSearch({ endpoint });

        search.moveHighlight(1);

        expect(search.highlightedIndex.value).toBe(-1);

        fetchMock.mockResolvedValueOnce(okResponse([row(1), row(2), row(3)]));
        search.onInput('bolt');
        await vi.advanceTimersByTimeAsync(300);

        search.moveHighlight(-1);
        expect(search.highlightedIndex.value).toBe(2);

        search.moveHighlight(1);
        expect(search.highlightedIndex.value).toBe(0);

        search.moveHighlight(1);
        expect(search.highlightedIndex.value).toBe(1);
    });

    it('reports a failed request', async () => {
        fetchMock.mockResolvedValueOnce({ ok: false, status: 500 } as Response);
        const search = useCardSearch({ endpoint });

        search.onInput('bolt');
        await vi.advanceTimersByTimeAsync(300);

        expect(search.error.value).toBe('Search failed.');
        expect(search.results.value).toEqual([]);
        expect(search.loading.value).toBe(false);
    });

    it('reset clears state', async () => {
        let signal: AbortSignal | undefined;

        fetchMock.mockImplementationOnce((_url: string, init: RequestInit) => {
            signal = init.signal as AbortSignal;

            return new Promise<Response>(() => {});
        });
        const search = useCardSearch({ endpoint });

        search.onInput('bolt');
        await vi.advanceTimersByTimeAsync(300);
        search.reset();

        expect(signal?.aborted).toBe(true);
        expect(search.query.value).toBe('');
        expect(search.results.value).toEqual([]);
        expect(search.error.value).toBeNull();
        expect(search.open.value).toBe(false);
        expect(search.loading.value).toBe(false);
    });
});
