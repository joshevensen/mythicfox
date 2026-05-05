<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { FilterDef, FilterOption } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import MfMoney from '@/components/MfMoney.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useMfToast } from '@/composables/useMfToast';
import { useTableState } from '@/composables/useTableState';
import { index as decksIndex } from '@/routes/decks';

type DeckRow = {
    id: number;
    set_id: number;
    product_id: number;
    tcgplayer_id: number;
    product_name: string;
    set_name: string;
    rarity: string;
    condition: string;
    market_price: number | null;
    low_price: number | null;
};

type DecksPayload = {
    data: DeckRow[];
    meta: {
        total: number;
        current_page: number;
        per_page: number;
    };
};

type Meta = {
    products: FilterOption[];
    sets_by_product: Record<string, FilterOption[]>;
};

const props = defineProps<{
    decks: DecksPayload;
    meta: Meta;
}>();

const page = usePage();
const { info } = useMfToast();

const tableState = useTableState({
    endpoint: decksIndex().url,
    filterKeys: ['product', 'sets'],
    defaultSort: { field: 'product_name', dir: 'asc' },
    inertiaOnly: ['decks', 'meta'],
});
const { hasActiveFilters, clearFilters: clearAllFilters } = tableState;

const selectedProductId = computed(
    () => tableState.filters.value.product ?? '',
);

const setOptions = computed<FilterOption[]>(() => {
    if (!selectedProductId.value) {
        return [];
    }

    return props.meta.sets_by_product[selectedProductId.value] ?? [];
});

const filters = computed<FilterDef[]>(() => [
    {
        kind: 'select',
        key: 'product',
        label: 'Product',
        options: props.meta.products,
    },
    {
        kind: 'enum',
        key: 'sets',
        label: 'Set',
        options: setOptions.value,
    },
]);

// Chained Set filter — drop selected set IDs that don't belong to a newly
// chosen product. Mirrors the same behaviour on Cards/Index.
let lastUrl = page.url;
watch(
    () => page.url,
    (next) => {
        const prev = new URL(lastUrl, 'http://localhost');
        const cur = new URL(next, 'http://localhost');
        lastUrl = next;

        const prevProduct = prev.searchParams.get('product') ?? '';
        const curProduct = cur.searchParams.get('product') ?? '';

        if (prevProduct === curProduct) {
            return;
        }

        const setsRaw = cur.searchParams.get('sets') ?? '';
        const selectedSetIds = setsRaw
            .split(',')
            .map((s) => s.trim())
            .filter((s) => s.length > 0);

        if (selectedSetIds.length === 0) {
            return;
        }

        const allowed = new Set(
            (props.meta.sets_by_product[curProduct] ?? []).map((o) => o.value),
        );
        const kept = selectedSetIds.filter((id) => allowed.has(id));
        const dropped = selectedSetIds.length - kept.length;

        if (dropped === 0) {
            return;
        }

        const productLabel =
            props.meta.products.find((p) => p.value === curProduct)?.label ??
            'this product';

        info(`Removed ${dropped} set filters not in ${productLabel}.`);

        const params = new URLSearchParams(cur.search);

        if (kept.length > 0) {
            params.set('sets', kept.join(','));
        } else {
            params.delete('sets');
        }

        params.delete('page');

        router.get(cur.pathname, Object.fromEntries(params.entries()), {
            preserveState: true,
            preserveScroll: true,
        });
    },
);

const columns: ColumnDef<DeckRow>[] = [
    { key: 'product_name', label: 'Deck', sortable: true },
    { key: 'set_name', label: 'Set', sortable: true },
    { key: 'rarity', label: 'Rarity', sortable: true },
    { key: 'condition', label: 'Condition', sortable: true },
    { key: 'market_price', label: 'Market', sortable: true, align: 'right' },
];
</script>

<template>
    <Head title="Decks" />

    <MfPageHeader title="Decks" />

    <MfTable
        :columns="columns"
        :rows="decks.data"
        :total="decks.meta.total"
        :page="tableState.page.value"
        :per-page="tableState.perPage.value"
        :sort="tableState.sort.value"
        row-key="id"
        :skeleton-rows="5"
        @update:page="tableState.setPage"
        @update:per-page="tableState.setPerPage"
        @update:sort="tableState.setSort"
    >
        <template #filters>
            <MfFilterPanel :filters="filters" :endpoint="decksIndex().url" />
        </template>

        <template #cell-product_name="{ row }">
            <span class="font-medium">{{ row.product_name }}</span>
        </template>

        <template #cell-market_price="{ row }">
            <MfMoney :cents="row.market_price" />
        </template>

        <template #empty>
            <div
                v-if="hasActiveFilters"
                class="flex flex-col items-center gap-3 py-8 text-center text-sm text-muted-foreground"
                data-test="decks-filtered-empty"
            >
                <p>No decks match these filters.</p>
                <button
                    type="button"
                    class="text-mf-orange hover:underline"
                    data-test="decks-clear-filters"
                    @click="clearAllFilters"
                >
                    Clear filters
                </button>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-3 py-10 text-center"
                data-test="decks-empty"
            >
                <p class="text-base font-medium text-foreground">
                    No decks yet.
                </p>
                <p class="max-w-md text-sm text-muted-foreground">
                    Sealed product (decks) is seeded by uploading a TCGPlayer
                    PricingCustomExport from the global import button.
                </p>
            </div>
        </template>

        <template #mobile-row="{ row }">
            <div
                class="flex flex-col gap-1 rounded-lg border border-border bg-card p-3"
                :data-test="`decks-card-${row.id}`"
            >
                <span class="text-sm font-medium text-foreground">
                    {{ row.product_name }}
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ row.set_name }} · {{ row.rarity }} · {{ row.condition }}
                </span>
                <div class="text-sm text-muted-foreground">
                    Market:
                    <MfMoney :cents="row.market_price" align="left" />
                </div>
            </div>
        </template>
    </MfTable>
</template>
