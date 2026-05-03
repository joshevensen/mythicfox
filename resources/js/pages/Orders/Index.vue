<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, onMounted, ref } from 'vue';
import MfDate from '@/components/MfDate.vue';
import type { FilterDef, FilterOption } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import MfMoney from '@/components/MfMoney.vue';
import MfMonospaceId from '@/components/MfMonospaceId.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import MfStatusPill from '@/components/MfStatusPill.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useOrdersImportModal } from '@/composables/useOrdersImportModal';
import { index as ordersIndex } from '@/routes/orders';

type OrderRow = {
    id: number;
    tcgplayer_order_number: string;
    tcgplayer_status: string;
    order_date: string | null;
    buyer_name: string;
    item_count: number | null;
    total_amount: number | null;
    tracking_number: string | null;
};

type OrdersPayload = {
    data: OrderRow[];
    meta: {
        total: number;
        current_page: number;
        per_page: number;
    };
};

type Meta = {
    statuses: FilterOption[];
    default_window_days: number;
};

const props = defineProps<{
    orders: OrdersPayload;
    meta: Meta;
}>();

const page = usePage();
const importModal = useOrdersImportModal();

// Dashboard quick-action shortcut: ?import=1 opens the import modal on mount.
// The modal is wired in 60-002; the composable carries the open state across.
onMounted(() => {
    const url = new URL(page.url, 'http://localhost');

    if (url.searchParams.get('import') === '1') {
        importModal.open();
    }
});

const filters = computed<FilterDef[]>(() => [
    {
        kind: 'enum',
        key: 'status',
        label: 'Status',
        options: props.meta.statuses,
    },
    {
        kind: 'date',
        key: 'order_date',
        label: 'Date range',
    },
]);

const columns: ColumnDef<OrderRow>[] = [
    {
        key: 'tcgplayer_order_number',
        label: 'Order #',
        sortable: true,
    },
    {
        key: 'order_date',
        label: 'Date',
        sortable: true,
    },
    {
        key: 'buyer_name',
        label: 'Buyer',
        sortable: true,
    },
    {
        key: 'item_count',
        label: 'Items',
        sortable: true,
        align: 'right',
    },
    {
        key: 'total_amount',
        label: 'Total',
        sortable: true,
        align: 'right',
    },
    {
        key: 'tcgplayer_status',
        label: 'Status',
        sortable: true,
    },
    {
        key: 'actions',
        label: '',
    },
];

const FILTER_KEYS = ['status', 'order_date_from', 'order_date_to'];

const currentUrl = (): URL => new URL(page.url, 'http://localhost');

const hasActiveFilters = computed(() =>
    FILTER_KEYS.some((key) => currentUrl().searchParams.has(key)),
);

const panelDrawerOpen = ref(false);

const showFiltersDrawer = (): void => {
    panelDrawerOpen.value = true;
};

const clearAllFilters = (): void => {
    router.get(
        ordersIndex().url,
        {},
        { preserveState: true, preserveScroll: true },
    );
};

const onImportClick = (): void => {
    importModal.open();
};
</script>

<template>
    <Head title="Orders" />

    <MfPageHeader title="Orders">
        <Button
            type="button"
            icon="pi pi-filter"
            label="Filters"
            severity="secondary"
            class="md:hidden"
            data-test="orders-mobile-filters"
            @click="showFiltersDrawer"
        />
        <Button
            type="button"
            icon="pi pi-upload"
            label="Import orders"
            data-test="orders-import-button"
            @click="onImportClick"
        />
    </MfPageHeader>

    <MfTable
        :endpoint="ordersIndex().url"
        :columns="columns"
        :rows="orders.data"
        :total="orders.meta.total"
        :default-sort="{ column: 'order_date', dir: 'desc' }"
        :inertia-only="['orders']"
        :selectable="true"
        :skeleton-rows="5"
    >
        <template #filters>
            <MfFilterPanel
                v-model:open="panelDrawerOpen"
                :filters="filters"
                :endpoint="ordersIndex().url"
            />
        </template>

        <template #bulk-actions>
            <!-- Bulk action button itself is wired in 60-003. The shell stays here. -->
            <span
                class="text-xs text-muted-foreground"
                data-test="orders-bulk-actions-placeholder"
            >
                Bulk actions coming
            </span>
        </template>

        <template #cell-tcgplayer_order_number="{ row }">
            <MfMonospaceId :value="row.tcgplayer_order_number" />
        </template>

        <template #cell-order_date="{ row }">
            <MfDate v-if="row.order_date" :value="row.order_date" />
            <span v-else class="text-muted-foreground">—</span>
        </template>

        <template #cell-item_count="{ row }">
            <span class="tabular-nums">{{ row.item_count ?? '—' }}</span>
        </template>

        <template #cell-total_amount="{ row }">
            <MfMoney :cents="row.total_amount" />
        </template>

        <template #cell-tcgplayer_status="{ row }">
            <MfStatusPill
                :status="row.tcgplayer_status"
                :tracking-number="row.tracking_number"
            />
        </template>

        <template #cell-actions>
            <!-- Action icons are stubbed here; 60-003 wires the click handlers -->
            <div class="flex justify-end gap-1" data-test="orders-row-actions">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                    aria-label="Print packing slip"
                    data-test="orders-print-stub"
                >
                    <i class="pi pi-printer" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                    aria-label="Open in TCGPlayer"
                    data-test="orders-tcgplayer-stub"
                >
                    <i class="pi pi-external-link" />
                </button>
            </div>
        </template>

        <template #empty>
            <div
                v-if="hasActiveFilters"
                class="flex flex-col items-center gap-3 py-8 text-center text-sm text-muted-foreground"
                data-test="orders-filtered-empty"
            >
                <p>No orders match these filters.</p>
                <button
                    type="button"
                    class="text-mf-orange hover:underline"
                    data-test="orders-clear-filters"
                    @click="clearAllFilters"
                >
                    Clear filters
                </button>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-3 py-10 text-center"
                data-test="orders-empty"
            >
                <p class="text-base font-medium text-foreground">
                    No orders yet.
                </p>
                <p class="max-w-md text-sm text-muted-foreground">
                    Import your first batch of TCGPlayer order exports.
                </p>
                <Button
                    type="button"
                    icon="pi pi-upload"
                    label="Import orders"
                    data-test="orders-empty-import"
                    @click="onImportClick"
                />
            </div>
        </template>

        <template #mobile-row="{ row, selected, toggleSelect }">
            <div
                :class="[
                    'flex flex-col gap-2 rounded-lg border bg-card p-3',
                    selected
                        ? 'border-mf-orange/60 bg-mf-orange/5'
                        : 'border-border',
                ]"
                :data-test="`orders-card-${row.id}`"
            >
                <div class="flex items-start justify-between gap-3">
                    <label class="flex items-start gap-2">
                        <input
                            type="checkbox"
                            :checked="selected"
                            class="mt-1"
                            :data-test="`orders-card-select-${row.id}`"
                            @change="toggleSelect"
                        />
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-foreground">
                                {{ row.buyer_name }}
                            </span>
                            <MfMonospaceId
                                :value="row.tcgplayer_order_number"
                                class="text-xs text-muted-foreground"
                            />
                        </div>
                    </label>
                    <MfStatusPill
                        :status="row.tcgplayer_status"
                        :tracking-number="row.tracking_number"
                    />
                </div>
                <div
                    class="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground"
                >
                    <MfDate v-if="row.order_date" :value="row.order_date" />
                    <span v-if="row.item_count !== null" aria-hidden="true"
                        >·</span
                    >
                    <span v-if="row.item_count !== null">
                        {{ row.item_count }} items
                    </span>
                    <span aria-hidden="true">·</span>
                    <MfMoney :cents="row.total_amount" align="left" />
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-11 min-w-11 items-center justify-center gap-1 rounded border border-border px-3 text-sm text-foreground hover:border-mf-orange hover:text-mf-orange"
                        aria-label="Print packing slip"
                        :data-test="`orders-card-print-${row.id}`"
                    >
                        <i class="pi pi-printer" />
                        <span>Print slip</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 min-w-11 items-center justify-center gap-1 rounded border border-border px-3 text-sm text-foreground hover:border-mf-orange hover:text-mf-orange"
                        aria-label="Open in TCGPlayer"
                        :data-test="`orders-card-tcgplayer-${row.id}`"
                    >
                        <i class="pi pi-external-link" />
                        <span>TCGPlayer</span>
                    </button>
                </div>
            </div>
        </template>
    </MfTable>
</template>
