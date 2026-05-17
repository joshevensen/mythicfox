<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, onMounted } from 'vue';
import MfDate from '@/components/MfDate.vue';
import type { FilterDef, FilterOption } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import MfMoney from '@/components/MfMoney.vue';
import MfMonospaceId from '@/components/MfMonospaceId.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import MfStatusPill from '@/components/MfStatusPill.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useGlobalImportModal } from '@/composables/useGlobalImportModal';
import { useMfConfirm } from '@/composables/useMfConfirm';
import { useMfToast } from '@/composables/useMfToast';
import { useTableState } from '@/composables/useTableState';
import { index as ordersIndex } from '@/routes/orders';
import packingSlipRoutes from '@/routes/orders/packing-slip';

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

type ImportResult =
    | {
          success: true;
          orders_inserted: number;
          orders_updated: number;
          line_items_created: number;
          line_items_unmatched_to_pdf: number;
          line_items_unmatched_to_inventory: number;
          errors: string[];
          warnings: string[];
          completed_at: string;
      }
    | {
          success: false;
          message?: string;
          errors?: string[];
          completed_at: string;
      };

type Meta = {
    statuses: FilterOption[];
    date_windows: FilterOption[];
    import_in_flight: boolean;
    import_last_result: ImportResult | null;
};

const props = defineProps<{
    orders: OrdersPayload;
    meta: Meta;
}>();

const page = usePage();
const importModal = useGlobalImportModal();

// Dashboard quick-action shortcut: ?import=1 opens the import modal on mount.
// The modal is wired in 60-002; the composable carries the open state across.
onMounted(() => {
    const url = new URL(page.url, 'http://localhost');

    if (url.searchParams.get('import') === '1') {
        importModal.open('orders');
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
        kind: 'select',
        key: 'date_window',
        label: 'Date range',
        options: props.meta.date_windows,
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

const tableState = useTableState({
    endpoint: ordersIndex().url,
    filterKeys: ['status', 'date_window'],
    defaultSort: { field: 'order_date', dir: 'desc' },
    inertiaOnly: ['orders'],
});
const { hasActiveFilters, clearFilters: clearAllFilters } = tableState;

const onImportClick = (): void => {
    importModal.open('orders');
};

const TCGPLAYER_ORDER_URL_BASE = 'https://sellerportal.tcgplayer.com/orders/';

const BULK_PRINT_HARD_CAP = 100;
const BULK_PRINT_CONFIRM_THRESHOLD = 25;

const printSlipUrl = (orderNumber: string): string =>
    packingSlipRoutes.show.url(orderNumber);

const tcgplayerUrl = (orderNumber: string): string =>
    `${TCGPLAYER_ORDER_URL_BASE}${orderNumber}`;

const openInNewTab = (url: string): void => {
    window.open(url, '_blank', 'noopener');
};

const onPrintRow = (orderNumber: string): void => {
    openInNewTab(printSlipUrl(orderNumber));
};

const onTcgplayerRow = (orderNumber: string): void => {
    openInNewTab(tcgplayerUrl(orderNumber));
};

const { confirm } = useMfConfirm();
const { error: toastError } = useMfToast();

const FILTER_SIGNATURE_KEYS = ['status', 'date_window'] as const;

const buildIdsUrl = (orderNumbers: string[]): string =>
    packingSlipRoutes.bulk.url({
        query: { ids: orderNumbers.join(',') },
    });

const buildSelectAllUrl = (): string => {
    const url = new URL(page.url, 'http://localhost');
    const query: Record<string, string> = { select_all: '1' };

    for (const key of FILTER_SIGNATURE_KEYS) {
        const v = url.searchParams.get(key);

        if (v !== null) {
            query[key] = v;
        }
    }

    return packingSlipRoutes.bulk.url({ query });
};

const onBulkPrint = (
    selectedKeys: Array<string | number>,
    selectAllMatching: boolean,
): void => {
    const count = selectAllMatching
        ? props.orders.meta.total
        : selectedKeys.length;
    const url = selectAllMatching
        ? buildSelectAllUrl()
        : buildIdsUrl(selectedKeys.map((key) => String(key)));

    if (count === 0) {
        return;
    }

    if (count > BULK_PRINT_HARD_CAP) {
        toastError(
            `Bulk print is capped at ${BULK_PRINT_HARD_CAP} orders. Narrow the selection and try again.`,
        );

        return;
    }

    if (count >= BULK_PRINT_CONFIRM_THRESHOLD) {
        confirm({
            title: `Print ${count} packing slips?`,
            body: 'Large batches can be hard to recover if printing is interrupted.',
            verb: 'Print',
            onConfirm: () => openInNewTab(url),
        });

        return;
    }

    openInNewTab(url);
};
</script>

<template>
    <Head title="Orders" />

    <MfPageHeader title="Orders">
        <Button
            type="button"
            :icon="
                meta.import_in_flight ? 'pi pi-spin pi-spinner' : 'pi pi-upload'
            "
            :label="meta.import_in_flight ? 'Importing…' : 'Import orders'"
            :disabled="meta.import_in_flight"
            data-test="orders-import-button"
            @click="onImportClick"
        />
    </MfPageHeader>

    <MfTable
        :columns="columns"
        :rows="orders.data"
        :total="orders.meta.total"
        :page="tableState.page.value"
        :per-page="tableState.perPage.value"
        :sort="tableState.sort.value"
        row-key="tcgplayer_order_number"
        :selectable="true"
        :skeleton-rows="5"
        @update:page="tableState.setPage"
        @update:per-page="tableState.setPerPage"
        @update:sort="tableState.setSort"
    >
        <template #filters>
            <MfFilterPanel :filters="filters" :endpoint="ordersIndex().url" />
        </template>

        <template #bulk-actions="{ selectedKeys, selectAllMatching }">
            <Button
                type="button"
                icon="pi pi-printer"
                label="Print packing slips"
                size="small"
                data-test="orders-bulk-print"
                @click="onBulkPrint(selectedKeys, selectAllMatching)"
            />
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

        <template #cell-actions="{ row }">
            <div class="flex justify-end gap-1" data-test="orders-row-actions">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                    aria-label="Print packing slip"
                    title="Print packing slip"
                    :data-test="`orders-print-${row.tcgplayer_order_number}`"
                    :data-href="printSlipUrl(row.tcgplayer_order_number)"
                    @click="onPrintRow(row.tcgplayer_order_number)"
                >
                    <i class="pi pi-print" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                    aria-label="Open in TCGPlayer"
                    title="Open in TCGPlayer"
                    :data-test="`orders-tcgplayer-${row.tcgplayer_order_number}`"
                    :data-href="tcgplayerUrl(row.tcgplayer_order_number)"
                    @click="onTcgplayerRow(row.tcgplayer_order_number)"
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
                        :data-test="`orders-card-print-${row.tcgplayer_order_number}`"
                        :data-href="printSlipUrl(row.tcgplayer_order_number)"
                        @click="onPrintRow(row.tcgplayer_order_number)"
                    >
                        <i class="pi pi-print" />
                        <span>Print slip</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 min-w-11 items-center justify-center gap-1 rounded border border-border px-3 text-sm text-foreground hover:border-mf-orange hover:text-mf-orange"
                        aria-label="Open in TCGPlayer"
                        :data-test="`orders-card-tcgplayer-${row.tcgplayer_order_number}`"
                        :data-href="tcgplayerUrl(row.tcgplayer_order_number)"
                        @click="onTcgplayerRow(row.tcgplayer_order_number)"
                    >
                        <i class="pi pi-external-link" />
                        <span>TCGPlayer</span>
                    </button>
                </div>
            </div>
        </template>
    </MfTable>
</template>
