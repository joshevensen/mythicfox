<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { onMounted, useId, useTemplateRef } from 'vue';
import { useCardSearch } from '@/composables/useCardSearch';
import type { CardSearchResult } from '@/composables/useCardSearch';
import { search as catalogSearch } from '@/routes/catalog';
import { show as cardShow } from '@/routes/catalog/products/cards';

type Props = {
    autofocus?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    autofocus: false,
});

const emit = defineEmits<{
    (e: 'navigated'): void;
    (e: 'dismiss'): void;
}>();

const {
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
} = useCardSearch({
    endpoint: (term) => catalogSearch.url({ query: { q: term } }),
});

const rootRef = useTemplateRef<HTMLElement>('rootRef');
const inputRef = useTemplateRef<{ $el: HTMLInputElement }>('inputRef');

const listboxId = useId();
const optionId = (index: number): string => `${listboxId}-option-${index}`;

onClickOutside(rootRef, close);

onMounted(() => {
    if (props.autofocus) {
        inputRef.value?.$el.focus();
    }
});

const select = (result: CardSearchResult): void => {
    router.visit(
        cardShow({ product: result.product_id, card: result.card_id }).url,
    );
    reset();
    emit('navigated');
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveHighlight(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveHighlight(-1);
    } else if (event.key === 'Enter') {
        if (open.value && highlighted.value) {
            event.preventDefault();
            select(highlighted.value);
        }
    } else if (event.key === 'Escape') {
        if (open.value) {
            close();
        } else {
            emit('dismiss');
        }
    }
};
</script>

<template>
    <div ref="rootRef" class="relative" data-test="card-search">
        <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
                ref="inputRef"
                type="search"
                placeholder="Search cards…"
                class="w-full"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listboxId"
                :aria-activedescendant="
                    open && highlightedIndex >= 0
                        ? optionId(highlightedIndex)
                        : undefined
                "
                aria-label="Search cards"
                autocomplete="off"
                data-test="card-search-input"
                :model-value="query"
                @update:model-value="onInput($event ?? '')"
                @keydown="onKeydown"
                @focus="reopen"
            />
        </IconField>

        <div
            v-if="open"
            class="absolute inset-x-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-md border border-border bg-background shadow-lg"
        >
            <ul role="listbox" :id="listboxId" data-test="card-search-results">
                <li
                    v-for="(result, i) in results"
                    :id="optionId(i)"
                    :key="result.printing_id"
                    role="option"
                    :aria-selected="i === highlightedIndex"
                    :class="[
                        'cursor-pointer px-3 py-2 text-sm',
                        i === highlightedIndex
                            ? 'bg-mf-orange/10 text-mf-orange'
                            : 'text-foreground',
                    ]"
                    data-test="card-search-option"
                    @mousedown.prevent
                    @click="select(result)"
                    @mouseenter="highlightedIndex = i"
                >
                    <div class="font-medium">{{ result.name }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ result.product_label }} · {{ result.set_name }} · #{{
                            result.number
                        }}
                        · {{ result.finish }}
                    </div>
                </li>
            </ul>

            <p
                v-if="loading && results.length === 0"
                class="px-3 py-2 text-sm text-muted-foreground"
                data-test="card-search-loading"
            >
                Searching…
            </p>
            <p
                v-if="searched && !loading && !error && results.length === 0"
                class="px-3 py-2 text-sm text-muted-foreground"
                data-test="card-search-empty"
            >
                No cards found
            </p>
            <p
                v-if="error"
                class="px-3 py-2 text-sm text-destructive"
                data-test="card-search-error"
            >
                {{ error }}
            </p>
        </div>
    </div>
</template>
