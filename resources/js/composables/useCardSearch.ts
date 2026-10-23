import { tryOnScopeDispose, useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export const CARD_SEARCH_MIN_LENGTH = 2;

export type CardSearchResult = {
    printing_id: number;
    card_id: number;
    product_id: number;
    name: string;
    product_label: string;
    set_name: string;
    number: string;
    finish: string;
};

type Options = {
    endpoint: (term: string) => string;
    debounceMs?: number;
};

type CardSearch = {
    query: Ref<string>;
    results: Ref<CardSearchResult[]>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    open: Ref<boolean>;
    searched: Ref<boolean>;
    highlightedIndex: Ref<number>;
    highlighted: ComputedRef<CardSearchResult | null>;
    onInput: (value: string) => void;
    moveHighlight: (delta: 1 | -1) => void;
    close: () => void;
    reopen: () => void;
    reset: () => void;
};

/**
 * Debounced, abortable card-name search state for the top nav combobox.
 * Framework-light on purpose: the endpoint is injected so it can be tested in isolation.
 */
export function useCardSearch(options: Options): CardSearch {
    const query = ref('');
    const results = ref<CardSearchResult[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const open = ref(false);
    const searched = ref(false);
    const highlightedIndex = ref(-1);

    let current: AbortController | null = null;

    const highlighted = computed<CardSearchResult | null>(
        () => results.value[highlightedIndex.value] ?? null,
    );

    const abortCurrent = (): void => {
        current?.abort();
        current = null;
    };

    const run = useDebounceFn(async (term: string): Promise<void> => {
        if (term !== query.value.trim()) {
            return;
        }

        abortCurrent();

        const controller = new AbortController();

        current = controller;
        loading.value = true;
        open.value = true;
        error.value = null;

        try {
            const res = await fetch(options.endpoint(term), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!res.ok) {
                throw new Error(`Search failed with status ${res.status}`);
            }

            const body = (await res.json()) as { data: CardSearchResult[] };

            if (current === controller) {
                results.value = body.data;
                highlightedIndex.value = body.data.length > 0 ? 0 : -1;
                searched.value = true;
            }
        } catch (e) {
            const aborted =
                e instanceof DOMException && e.name === 'AbortError';

            if (!aborted && current === controller) {
                error.value = 'Search failed.';
                results.value = [];
                highlightedIndex.value = -1;
                searched.value = true;
            }
        } finally {
            if (current === controller) {
                loading.value = false;
            }
        }
    }, options.debounceMs ?? 300);

    const onInput = (value: string): void => {
        query.value = value;

        const term = value.trim();

        if (term.length < CARD_SEARCH_MIN_LENGTH) {
            abortCurrent();
            results.value = [];
            open.value = false;
            loading.value = false;
            searched.value = false;
            error.value = null;
            highlightedIndex.value = -1;

            return;
        }

        void run(term);
    };

    const moveHighlight = (delta: 1 | -1): void => {
        const count = results.value.length;

        if (count === 0) {
            return;
        }

        open.value = true;
        highlightedIndex.value =
            (highlightedIndex.value + delta + count) % count;
    };

    const close = (): void => {
        open.value = false;
    };

    const reopen = (): void => {
        if (
            query.value.trim().length >= CARD_SEARCH_MIN_LENGTH &&
            (results.value.length > 0 || searched.value)
        ) {
            open.value = true;
        }
    };

    const reset = (): void => {
        abortCurrent();
        query.value = '';
        results.value = [];
        error.value = null;
        searched.value = false;
        loading.value = false;
        highlightedIndex.value = -1;
        open.value = false;
    };

    tryOnScopeDispose(abortCurrent);

    return {
        query,
        results,
        loading,
        error,
        open,
        searched,
        highlightedIndex,
        highlighted,
        onInput,
        moveHighlight,
        close,
        reopen,
        reset,
    };
}
