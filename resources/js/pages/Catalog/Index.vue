<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, ref, watch } from 'vue';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import type { FilterDef, FilterOption } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import MfMonospaceId from '@/components/MfMonospaceId.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useGlobalImportModal } from '@/composables/useGlobalImportModal';
import { useMfToast } from '@/composables/useMfToast';
import { useTableState } from '@/composables/useTableState';
import RowExpand from '@/pages/Catalog/RowExpand.vue';
import { index as cardsIndex } from '@/routes/catalog';

type CardRow = {
    key: string;
    id: number;
    set_id: number;
    product_id: number;
    name: string;
    number: string;
    set_name: string;
    rarity: string;
};

type CardsPayload = {
    data: CardRow[];
    meta: {
        total: number;
        current_page: number;
        per_page: number;
    };
};

type Variant = {
    finish: string;
    tcgplayer_id: number | null;
    market_price: number | null;
    low_price: number | null;
};

type StaleEntry = {
    id: number;
    name: string;
    priced_at: string | null;
    is_stale: boolean;
};

type ImportResult = {
    success: boolean;
    rows_processed?: number;
    products_touched?: number;
    product_label?: string;
    message?: string;
    completed_at: string;
};

type Meta = {
    products: FilterOption[];
    sets_by_product: Record<string, FilterOption[]>;
    products_priced_at: StaleEntry[];
    has_any_cards: boolean;
    import_in_flight: boolean;
    import_last_result: ImportResult | null;
    upload_error: string | null;
};

const props = defineProps<{
    cards: CardsPayload;
    variants: Record<string, Variant[]>;
    meta: Meta;
}>();

const page = usePage();
const { info } = useMfToast();
const importModal = useGlobalImportModal();

const tableState = useTableState({
    endpoint: cardsIndex().url,
    filterKeys: ['product', 'sets'],
    defaultSort: { field: 'name', dir: 'asc' },
    inertiaOnly: ['cards', 'variants', 'meta'],
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

// Chained Set filter: when Product changes, drop selected set IDs that don't
// belong to the new product. The MfFilterPanel writes filters to the URL —
// we watch page.url here and synthesize a re-navigation if necessary.
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

const columns: ColumnDef<CardRow>[] = [
    {
        key: 'name',
        label: 'Card Name',
        sortable: true,
    },
    {
        key: 'number',
        label: 'Number',
        sortable: true,
    },
    {
        key: 'set_name',
        label: 'Set Name',
        sortable: true,
    },
    {
        key: 'rarity',
        label: 'Rarity',
        sortable: true,
    },
];

const onUploadClick = (): void => {
    importModal.open('catalog');
};

const errorBanner = ref<string | null>(props.meta.upload_error);

watch(
    () => props.meta.upload_error,
    (next) => {
        if (next) {
            errorBanner.value = next;
        }
    },
);

const dismissErrorBanner = (): void => {
    errorBanner.value = null;
};

const dayDiff = (iso: string): number => {
    const then = new Date(iso).getTime();
    const now = Date.now();

    return Math.max(0, Math.floor((now - then) / (24 * 60 * 60 * 1000)));
};

const stalenessLabel = (entry: StaleEntry): string => {
    if (!entry.priced_at) {
        return `${entry.name}: never refreshed`;
    }

    const days = dayDiff(entry.priced_at);

    if (days === 0) {
        return `${entry.name} refreshed today`;
    }

    if (days === 1) {
        return `${entry.name} refreshed 1 day ago`;
    }

    return `${entry.name} refreshed ${days} days ago`;
};
</script>

<template>
    <Head title="Cards" />

    <MfPageHeader title="Cards">
        <div
            v-if="meta.products_priced_at.length > 0"
            class="mr-2 hidden flex-col gap-0.5 text-xs sm:flex"
            data-test="catalog-staleness"
        >
            <span
                v-for="entry in meta.products_priced_at"
                :key="entry.id"
                :class="
                    entry.is_stale
                        ? 'text-amber-600 dark:text-amber-400'
                        : 'text-muted-foreground'
                "
                :data-test="`catalog-stale-${entry.id}`"
            >
                {{ stalenessLabel(entry) }}
            </span>
        </div>
    </MfPageHeader>

    <MfErrorBanner
        v-if="errorBanner"
        class="mb-4"
        title="Import failed"
        :message="errorBanner"
        @dismiss="dismissErrorBanner"
    />

    <MfTable
        :columns="columns"
        :rows="cards.data"
        :total="cards.meta.total"
        :page="tableState.page.value"
        :per-page="tableState.perPage.value"
        :sort="tableState.sort.value"
        row-key="key"
        :expandable="true"
        :skeleton-rows="5"
        @update:page="tableState.setPage"
        @update:per-page="tableState.setPerPage"
        @update:sort="tableState.setSort"
    >
        <template #filters>
            <MfFilterPanel :filters="filters" :endpoint="cardsIndex().url" />
        </template>

        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>

        <template #cell-number="{ row }">
            <MfMonospaceId :value="row.number" />
        </template>

        <template #expand-row="{ row }">
            <RowExpand :variants="variants[row.key] ?? []" />
        </template>

        <template #empty>
            <div
                v-if="hasActiveFilters"
                class="flex flex-col items-center gap-3 py-8 text-center text-sm text-muted-foreground"
                data-test="catalog-filtered-empty"
            >
                <p>No cards match these filters.</p>
                <button
                    type="button"
                    class="text-mf-orange hover:underline"
                    data-test="catalog-clear-filters"
                    @click="clearAllFilters"
                >
                    Clear filters
                </button>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-3 py-10 text-center"
                data-test="catalog-empty"
            >
                <p class="text-base font-medium text-foreground">
                    No cards in catalog.
                </p>
                <p class="max-w-md text-sm text-muted-foreground">
                    Run
                    <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs"
                        >php artisan catalog:sync</code
                    >
                    to import, or upload a TCGPlayer PricingCustomExport.
                </p>
                <Button
                    type="button"
                    icon="pi pi-upload"
                    label="Import catalog"
                    data-test="catalog-empty-upload"
                    @click="onUploadClick"
                />
            </div>
        </template>

        <template #mobile-row="{ row, expanded, toggleExpand }">
            <div
                class="flex flex-col gap-2 rounded-lg border border-border bg-card p-3"
                :data-test="`catalog-card-${row.key}`"
                role="button"
                tabindex="0"
                @click="toggleExpand"
                @keydown.enter.prevent="toggleExpand"
                @keydown.space.prevent="toggleExpand"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-foreground">
                            {{ row.name }}
                        </span>
                        <span
                            class="text-xs text-muted-foreground"
                            data-test="catalog-card-meta"
                        >
                            #{{ row.number }} · {{ row.set_name }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ row.rarity }}
                        </span>
                    </div>
                    <i
                        :class="[
                            'pi text-xs text-muted-foreground',
                            expanded ? 'pi-chevron-down' : 'pi-chevron-right',
                        ]"
                        aria-hidden="true"
                    />
                </div>
                <div v-if="expanded" class="-mx-3 mt-1 -mb-3">
                    <RowExpand :variants="variants[row.key] ?? []" />
                </div>
            </div>
        </template>
    </MfTable>
</template>
