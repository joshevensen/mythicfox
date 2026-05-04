<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CatalogUploadModal from '@/components/catalog/CatalogUploadModal.vue';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import type { FilterDef, FilterOption } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import MfMonospaceId from '@/components/MfMonospaceId.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useCatalogUploadModal } from '@/composables/useCatalogUploadModal';
import { useMfToast } from '@/composables/useMfToast';
import { useTableState } from '@/composables/useTableState';
import RowExpand from '@/pages/Cards/RowExpand.vue';
import { index as cardsIndex } from '@/routes/cards';

type CardRow = {
    key: string;
    set_id: number;
    product_id: number;
    product_name: string;
    number: string;
    set_name: string;
    rarity: string;
    total_qty: number;
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
    condition: string;
    quantity: number;
    tcgplayer_id: number;
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
const { info, success } = useMfToast();
const uploadModal = useCatalogUploadModal();

const tableState = useTableState({
    endpoint: cardsIndex().url,
    filterKeys: ['product', 'sets', 'in_stock'],
    defaultSort: { field: 'product_name', dir: 'asc' },
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
    {
        kind: 'boolean',
        key: 'in_stock',
        label: 'In stock',
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
        key: 'product_name',
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
    {
        key: 'total_qty',
        label: 'Total Qty',
        sortable: true,
        align: 'right',
    },
];

const onUploadClick = (): void => {
    uploadModal.open();
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

// Polling pattern mirrors Orders/Index.vue (60-002): while the catalog import
// job is in flight, partial-reload the catalog props every 2s so the table
// auto-refreshes when the job completes.
const importPollHandle = ref<number | null>(null);
const wasInFlight = ref(props.meta.import_in_flight);

const stopPolling = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    if (importPollHandle.value !== null) {
        window.clearInterval(importPollHandle.value);
        importPollHandle.value = null;
    }
};

const startPolling = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    if (importPollHandle.value !== null) {
        return;
    }

    importPollHandle.value = window.setInterval(() => {
        router.reload({ only: ['cards', 'variants', 'meta'] });
    }, 2000);
};

watch(
    () => props.meta.import_in_flight,
    (next) => {
        if (next) {
            wasInFlight.value = true;
            startPolling();

            return;
        }

        stopPolling();

        if (!wasInFlight.value) {
            return;
        }

        wasInFlight.value = false;

        const last = props.meta.import_last_result;

        if (last && last.success) {
            const rows = last.rows_processed ?? 0;
            const label = last.product_label ?? 'the catalog';
            success(`Refreshed ${rows} cards across ${label}.`);
        } else if (last && !last.success) {
            const message = last.message ?? 'Catalog import failed.';
            errorBanner.value = message;
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.meta.import_in_flight) {
        startPolling();
    }
});

onUnmounted(stopPolling);

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
        <Button
            type="button"
            :icon="
                meta.import_in_flight ? 'pi pi-spin pi-spinner' : 'pi pi-upload'
            "
            :label="
                meta.import_in_flight
                    ? 'Importing…'
                    : 'Upload PricingCustomExport'
            "
            :disabled="meta.import_in_flight"
            class="fixed right-4 bottom-4 z-30 shadow-lg md:static md:right-auto md:bottom-auto md:shadow-none"
            data-test="catalog-upload-button"
            @click="onUploadClick"
        />
    </MfPageHeader>

    <MfErrorBanner
        v-if="errorBanner"
        class="mb-4"
        title="Import failed"
        :message="errorBanner"
        @dismiss="dismissErrorBanner"
    />

    <CatalogUploadModal />

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
            <MfFilterPanel
                :filters="filters"
                :endpoint="cardsIndex().url"
            />
        </template>

        <template #cell-product_name="{ row }">
            <span class="font-medium">{{ row.product_name }}</span>
        </template>

        <template #cell-number="{ row }">
            <MfMonospaceId :value="row.number" />
        </template>

        <template #cell-total_qty="{ row }">
            <span class="tabular-nums">{{ row.total_qty }}</span>
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
                    No cards yet.
                </p>
                <p class="max-w-md text-sm text-muted-foreground">
                    Upload a TCGPlayer PricingCustomExport to seed it.
                </p>
                <Button
                    type="button"
                    icon="pi pi-upload"
                    label="Upload PricingCustomExport"
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
                            {{ row.product_name }}
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
                <div class="text-sm text-muted-foreground">
                    Total Qty:
                    <span class="text-foreground tabular-nums">{{
                        row.total_qty
                    }}</span>
                </div>
                <div v-if="expanded" class="-mx-3 mt-1 -mb-3">
                    <RowExpand :variants="variants[row.key] ?? []" />
                </div>
            </div>
        </template>
    </MfTable>
</template>
