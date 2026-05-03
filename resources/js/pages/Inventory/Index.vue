<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, ref, watch } from 'vue';
import InventoryExportModal from '@/components/inventory/InventoryExportModal.vue';
import type { FilterDef, FilterOption } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import MfMoney from '@/components/MfMoney.vue';
import MfMoneyInput from '@/components/MfMoneyInput.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import MfQtyInput from '@/components/MfQtyInput.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useInlineCellSave } from '@/composables/useInlineCellSave';
import { useMfConfirm } from '@/composables/useMfConfirm';
import { useMfToast } from '@/composables/useMfToast';
import {
    destroy as destroyInventory,
    index as inventoryIndex,
    update as updateInventory,
} from '@/routes/inventory';
import {
    clearOverrides as bulkClearOverrides,
    markOutOfStock as bulkMarkOutOfStock,
} from '@/routes/inventory/bulk';

type InventoryRow = {
    id: number;
    card_id: number;
    product_name: string;
    number: string;
    condition: string;
    rarity: string;
    market_price: number | null;
    low_price: number | null;
    calculated_price: number | null;
    override_price: number | null;
    quantity: number;
};

type RowsPayload = {
    data: InventoryRow[];
    meta: {
        total: number;
        current_page: number;
        per_page: number;
    };
};

type StaleEntry = {
    id: number;
    name: string;
    priced_at: string | null;
    is_stale: boolean;
};

type Meta = {
    filters_complete: boolean;
    products: FilterOption[];
    sets_by_product: Record<string, FilterOption[]>;
    conditions: FilterOption[];
    products_priced_at: StaleEntry[];
    override_count: number;
};

const props = defineProps<{
    rows: RowsPayload;
    meta: Meta;
}>();

const page = usePage();
const { confirm } = useMfConfirm();
const { success, error: toastError } = useMfToast();

const currentUrl = (): URL => new URL(page.url, 'http://localhost');

const selectedProductId = computed(
    () => currentUrl().searchParams.get('product') ?? '',
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
        kind: 'enum',
        key: 'conditions',
        label: 'Condition',
        options: props.meta.conditions,
    },
    {
        kind: 'boolean',
        key: 'has_override',
        label: 'Has override',
    },
    {
        kind: 'boolean',
        key: 'in_stock',
        label: 'In stock',
    },
]);

// Chained Set filter: when Product changes, drop selected set IDs that don't
// belong to the new product. Same shape as Catalog/Index.
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

        if (kept.length === selectedSetIds.length) {
            return;
        }

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

const columns: ColumnDef<InventoryRow>[] = [
    { key: 'product_name', label: 'Card Name', sortable: true },
    { key: 'number', label: 'Number', sortable: true },
    { key: 'market_price', label: 'Market', sortable: true, align: 'right' },
    { key: 'low_price', label: 'Low', sortable: true, align: 'right' },
    {
        key: 'calculated_price',
        label: 'Calculated',
        sortable: true,
        align: 'right',
    },
    {
        key: 'override_price',
        label: 'Override',
        sortable: true,
        align: 'right',
    },
    { key: 'quantity', label: 'Qty', sortable: true, align: 'right' },
    { key: 'actions', label: '' },
];

const panelDrawerOpen = ref(false);

const showFiltersDrawer = (): void => {
    panelDrawerOpen.value = true;
};

// Local row cache lets inline edits update the visible table immediately
// (last-write-wins from server response). Inertia's full prop refresh on
// navigation overwrites this — that's fine, by then any in-flight saves are
// done.
const localRows = ref<InventoryRow[]>([...props.rows.data]);
const localOverrideCount = ref<number>(props.meta.override_count);

watch(
    () => props.rows.data,
    (next) => {
        localRows.value = [...next];
    },
    { deep: true },
);
watch(
    () => props.meta.override_count,
    (next) => {
        localOverrideCount.value = next;
    },
);

const visibleRows = computed(() => localRows.value);

const replaceLocalRow = (next: InventoryRow): void => {
    const index = localRows.value.findIndex((r) => r.id === next.id);

    if (index >= 0) {
        const copy = [...localRows.value];
        copy[index] = next;
        localRows.value = copy;
    }
};

type SavePayload = { quantity?: number; override_price?: number | null };
type SaveResult = {
    inventory: InventoryRow;
    override_count: number;
};

const fetchJson = async <TResult,>(
    url: string,
    method: string,
    body: unknown,
    signal: AbortSignal,
): Promise<TResult> => {
    const csrfToken =
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    const response = await fetch(url, {
        method,
        signal,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        let detail = '';

        try {
            const data = await response.json();

            detail =
                typeof data === 'object' && data !== null && 'message' in data
                    ? String((data as { message?: string }).message ?? '')
                    : '';
        } catch {
            // ignore
        }

        throw new Error(detail || `Save failed (${response.status})`);
    }

    return (await response.json()) as TResult;
};

const inlineSave = useInlineCellSave<
    { id: number; payload: SavePayload },
    SaveResult
>(({ id, payload }, signal) =>
    fetchJson<SaveResult>(
        updateInventory({ inventory: id }).url,
        'PATCH',
        payload,
        signal,
    ),
);

type EditState =
    | { kind: 'qty'; id: number; value: number; original: number }
    | {
          kind: 'override';
          id: number;
          value: number | null;
          original: number | null;
      }
    | null;

const editing = ref<EditState>(null);

const beginEditQty = (row: InventoryRow): void => {
    editing.value = {
        kind: 'qty',
        id: row.id,
        value: row.quantity,
        original: row.quantity,
    };
};

const beginEditOverride = (row: InventoryRow): void => {
    editing.value = {
        kind: 'override',
        id: row.id,
        value: row.override_price,
        original: row.override_price,
    };
};

const cancelEdit = (): void => {
    editing.value = null;
};

const cellKey = (id: number, field: 'quantity' | 'override_price'): string =>
    `${id}:${field}`;

const commitEdit = async (): Promise<void> => {
    const e = editing.value;

    if (!e) {
        return;
    }

    if (e.kind === 'qty') {
        const next = e.value;

        if (next === e.original) {
            editing.value = null;

            return;
        }

        const id = e.id;
        editing.value = null;

        const result = await inlineSave.dispatch(cellKey(id, 'quantity'), {
            id,
            payload: { quantity: next },
        });

        if (result) {
            replaceLocalRow(result.inventory);
            localOverrideCount.value = result.override_count;
        }

        return;
    }

    const next = e.value;

    if (next === e.original) {
        editing.value = null;

        return;
    }

    const id = e.id;
    editing.value = null;

    const result = await inlineSave.dispatch(cellKey(id, 'override_price'), {
        id,
        payload: { override_price: next },
    });

    if (result) {
        replaceLocalRow(result.inventory);
        localOverrideCount.value = result.override_count;
    }
};

const isEditingQty = (id: number): boolean =>
    editing.value !== null &&
    editing.value.kind === 'qty' &&
    editing.value.id === id;

const isEditingOverride = (id: number): boolean =>
    editing.value !== null &&
    editing.value.kind === 'override' &&
    editing.value.id === id;

const cellState = (id: number, field: 'quantity' | 'override_price') =>
    inlineSave.stateFor(cellKey(id, field));

const onResetRow = (row: InventoryRow): void => {
    confirm({
        title: 'Reset to calculated price?',
        body: 'The override on this row will be cleared and the effective price will revert to the calculated price.',
        verb: 'Reset',
        onConfirm: async () => {
            const result = await inlineSave.dispatch(
                cellKey(row.id, 'override_price'),
                { id: row.id, payload: { override_price: null } },
            );

            if (result) {
                replaceLocalRow(result.inventory);
                localOverrideCount.value = result.override_count;
                success('Override cleared.');
            }
        },
    });
};

const onRemoveRow = (row: InventoryRow): void => {
    confirm({
        title: 'Remove from inventory?',
        body: 'Quantity will be set to 0 and any override will be cleared. The inventory row stays around so the history is preserved.',
        verb: 'Remove',
        destructive: true,
        onConfirm: async () => {
            const result = await inlineSave.dispatch(
                cellKey(row.id, 'quantity'),
                {
                    id: row.id,
                    payload: {},
                },
            );

            // Use destroy endpoint for the actual mutation; the dispatch
            // above just gives us a single coalesced spinner. Reuse fetchJson
            // for a consistent error path.
            try {
                const fresh = await fetchJson<SaveResult>(
                    destroyInventory({ inventory: row.id }).url,
                    'DELETE',
                    undefined,
                    new AbortController().signal,
                );

                replaceLocalRow(fresh.inventory);
                localOverrideCount.value = fresh.override_count;
                success('Removed from inventory.');
            } catch (e) {
                toastError(e instanceof Error ? e.message : 'Remove failed.');
            }

            void result;
        },
    });
};

// --- bulk actions -----------------------------------------------------------

type BulkResult = { updated: number; override_count: number };

const runBulk = async (
    url: string,
    selectedKeys: Array<string | number>,
    selectAllMatching: boolean,
): Promise<BulkResult | null> => {
    const ids = selectedKeys
        .map((k) => Number(k))
        .filter((n) => Number.isFinite(n));

    if (selectAllMatching) {
        const url2 = new URL(page.url, 'http://localhost');

        try {
            return await fetchJson<BulkResult>(
                url,
                'POST',
                {
                    select_all: true,
                    product: url2.searchParams.get('product'),
                    sets: url2.searchParams.get('sets'),
                    conditions: url2.searchParams.get('conditions'),
                    has_override: url2.searchParams.get('has_override'),
                    in_stock: url2.searchParams.get('in_stock'),
                },
                new AbortController().signal,
            );
        } catch (e) {
            toastError(e instanceof Error ? e.message : 'Bulk action failed.');

            return null;
        }
    }

    if (ids.length === 0) {
        toastError('No rows selected.');

        return null;
    }

    try {
        return await fetchJson<BulkResult>(
            url,
            'POST',
            { ids },
            new AbortController().signal,
        );
    } catch (e) {
        toastError(e instanceof Error ? e.message : 'Bulk action failed.');

        return null;
    }
};

const onBulkClearOverrides = (
    selectedKeys: Array<string | number>,
    selectAllMatching: boolean,
): void => {
    const count = selectAllMatching
        ? props.rows.meta.total
        : selectedKeys.length;

    confirm({
        title: 'Clear overrides?',
        body: `Clear overrides on ${count} row${count === 1 ? '' : 's'}? Effective price will revert to the calculated price.`,
        verb: 'Clear',
        onConfirm: async () => {
            const result = await runBulk(
                bulkClearOverrides().url,
                selectedKeys,
                selectAllMatching,
            );

            if (result) {
                success(
                    `Cleared overrides on ${result.updated} row${result.updated === 1 ? '' : 's'}.`,
                );
                router.reload({ only: ['rows', 'meta'] });
            }
        },
    });
};

const onBulkMarkOutOfStock = (
    selectedKeys: Array<string | number>,
    selectAllMatching: boolean,
): void => {
    const count = selectAllMatching
        ? props.rows.meta.total
        : selectedKeys.length;

    confirm({
        title: 'Set quantity to 0?',
        body: `Set quantity to 0 on ${count} row${count === 1 ? '' : 's'}? Overrides are kept (different from per-row Remove, which clears them).`,
        verb: 'Mark out of stock',
        destructive: true,
        onConfirm: async () => {
            const result = await runBulk(
                bulkMarkOutOfStock().url,
                selectedKeys,
                selectAllMatching,
            );

            if (result) {
                success(
                    `Marked ${result.updated} row${result.updated === 1 ? '' : 's'} out of stock.`,
                );
                router.reload({ only: ['rows', 'meta'] });
            }
        },
    });
};

// --- override-count indicator ----------------------------------------------

const onOverrideCountClick = (): void => {
    const url = currentUrl();
    const params = url.searchParams;
    const isOn = params.get('has_override') === '1';

    if (isOn) {
        params.delete('has_override');
    } else {
        params.set('has_override', '1');
    }

    params.delete('page');

    router.get(url.pathname, Object.fromEntries(params.entries()), {
        preserveState: true,
        preserveScroll: true,
    });
};

// --- staleness indicator ---------------------------------------------------

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
        return `${entry.name} prices are 1 day old`;
    }

    return `${entry.name} prices are ${days} days old`;
};

const exportModalOpen = ref(false);

const onExportClick = (): void => {
    exportModalOpen.value = true;
};

const onExportCompleted = (summary: {
    rows: number;
    changed: number;
}): void => {
    success(
        `Pricing CSV downloaded — ${summary.rows} row${summary.rows === 1 ? '' : 's'}, ${summary.changed} changed.`,
    );
    router.reload({ only: ['rows', 'meta'] });
};

// Dashboard quick-action shortcut: ?export=1 opens the export modal on mount.
const initialUrl = new URL(page.url, 'http://localhost');

if (initialUrl.searchParams.get('export') === '1') {
    exportModalOpen.value = true;
}
</script>

<template>
    <Head title="Inventory" />

    <InventoryExportModal
        v-model:visible="exportModalOpen"
        @completed="onExportCompleted"
    />

    <MfPageHeader title="Inventory">
        <button
            type="button"
            class="hidden text-xs font-medium text-mf-orange hover:underline sm:inline-block"
            data-test="inventory-override-count"
            @click="onOverrideCountClick"
        >
            {{ localOverrideCount }} override{{
                localOverrideCount === 1 ? '' : 's'
            }}
            active
        </button>

        <div
            v-if="meta.products_priced_at.length > 0"
            class="mr-2 hidden flex-col gap-0.5 text-xs sm:flex"
            data-test="inventory-staleness"
        >
            <span
                v-for="entry in meta.products_priced_at"
                :key="entry.id"
                :class="
                    entry.is_stale
                        ? 'text-amber-600 dark:text-amber-400'
                        : 'text-muted-foreground'
                "
                :data-test="`inventory-stale-${entry.id}`"
            >
                {{ stalenessLabel(entry) }}
            </span>
        </div>

        <Button
            type="button"
            icon="pi pi-filter"
            label="Filters"
            severity="secondary"
            class="md:hidden"
            data-test="inventory-mobile-filters"
            @click="showFiltersDrawer"
        />

        <Button
            type="button"
            icon="pi pi-dollar"
            label="Export Pricing"
            class="fixed right-4 bottom-4 z-30 shadow-lg md:static md:right-auto md:bottom-auto md:shadow-none"
            data-test="inventory-export-button"
            @click="onExportClick"
        />
    </MfPageHeader>

    <div
        v-if="!meta.filters_complete"
        class="mt-4 rounded-lg border-2 border-dashed border-border bg-muted/20 px-6 py-12 text-center"
        data-test="inventory-empty-filters"
    >
        <MfFilterPanel
            v-model:open="panelDrawerOpen"
            :filters="filters"
            :endpoint="inventoryIndex().url"
        />
        <p class="mt-6 text-base font-medium text-foreground">
            Pick a product, set, and condition to view inventory.
        </p>
    </div>

    <MfTable
        v-else
        :endpoint="inventoryIndex().url"
        :columns="columns"
        :rows="visibleRows"
        :total="rows.meta.total"
        row-key="id"
        :default-sort="{ column: 'product_name', dir: 'asc' }"
        :inertia-only="['rows', 'meta']"
        :selectable="true"
        :skeleton-rows="5"
    >
        <template #filters>
            <MfFilterPanel
                v-model:open="panelDrawerOpen"
                :filters="filters"
                :endpoint="inventoryIndex().url"
            />
        </template>

        <template #bulk-actions="{ selectedKeys, selectAllMatching }">
            <Button
                type="button"
                icon="pi pi-eraser"
                label="Clear overrides"
                severity="secondary"
                size="small"
                data-test="inventory-bulk-clear"
                @click="onBulkClearOverrides(selectedKeys, selectAllMatching)"
            />
            <Button
                type="button"
                icon="pi pi-ban"
                label="Mark out of stock"
                severity="secondary"
                size="small"
                data-test="inventory-bulk-out-of-stock"
                @click="onBulkMarkOutOfStock(selectedKeys, selectAllMatching)"
            />
        </template>

        <template #cell-product_name="{ row }">
            <span class="font-medium">{{ row.product_name }}</span>
        </template>

        <template #cell-number="{ row }">
            <span class="font-mono text-sm">{{ row.number }}</span>
        </template>

        <template #cell-market_price="{ row }">
            <MfMoney :cents="row.market_price" />
        </template>

        <template #cell-low_price="{ row }">
            <MfMoney :cents="row.low_price" />
        </template>

        <template #cell-calculated_price="{ row }">
            <MfMoney :cents="row.calculated_price" />
        </template>

        <template #cell-override_price="{ row }">
            <div
                v-if="!isEditingOverride(row.id)"
                class="flex items-center justify-end gap-1"
                role="button"
                tabindex="0"
                :data-test="`inventory-override-display-${row.id}`"
                :class="
                    cellState(row.id, 'override_price').error
                        ? 'rounded border border-red-500/60'
                        : null
                "
                :title="cellState(row.id, 'override_price').error ?? undefined"
                @click="beginEditOverride(row)"
                @keydown.enter.prevent="beginEditOverride(row)"
                @keydown.space.prevent="beginEditOverride(row)"
            >
                <i
                    v-if="cellState(row.id, 'override_price').saving"
                    class="pi pi-spin pi-spinner text-xs text-muted-foreground"
                />
                <MfMoney :cents="row.override_price" />
            </div>
            <div
                v-else
                class="flex justify-end"
                :data-test="`inventory-override-edit-${row.id}`"
            >
                <MfMoneyInput
                    v-if="editing && editing.kind === 'override'"
                    :model-value="editing.value"
                    nullable
                    autofocus
                    @update:model-value="
                        (v) =>
                            editing &&
                            editing.kind === 'override' &&
                            (editing.value = v)
                    "
                    @keydown.enter="commitEdit"
                    @keydown.escape="cancelEdit"
                    @blur="commitEdit"
                />
            </div>
        </template>

        <template #cell-quantity="{ row }">
            <div
                v-if="!isEditingQty(row.id)"
                class="flex items-center justify-end gap-1 tabular-nums"
                role="button"
                tabindex="0"
                :data-test="`inventory-qty-display-${row.id}`"
                :class="
                    cellState(row.id, 'quantity').error
                        ? 'rounded border border-red-500/60'
                        : null
                "
                :title="cellState(row.id, 'quantity').error ?? undefined"
                @click="beginEditQty(row)"
                @keydown.enter.prevent="beginEditQty(row)"
                @keydown.space.prevent="beginEditQty(row)"
            >
                <i
                    v-if="cellState(row.id, 'quantity').saving"
                    class="pi pi-spin pi-spinner text-xs text-muted-foreground"
                />
                <span>{{ row.quantity }}</span>
            </div>
            <div
                v-else
                class="flex justify-end"
                :data-test="`inventory-qty-edit-${row.id}`"
            >
                <MfQtyInput
                    v-if="editing && editing.kind === 'qty'"
                    :model-value="editing.value"
                    @update:model-value="
                        (v: number) =>
                            editing &&
                            editing.kind === 'qty' &&
                            (editing.value = v)
                    "
                    @keydown.enter="commitEdit"
                    @keydown.escape="cancelEdit"
                    @blur="commitEdit"
                />
            </div>
        </template>

        <template #cell-actions="{ row }">
            <div class="flex justify-end gap-1">
                <Button
                    v-if="row.override_price !== null"
                    type="button"
                    icon="pi pi-refresh"
                    severity="secondary"
                    text
                    rounded
                    aria-label="Reset to calculated price"
                    :data-test="`inventory-row-reset-${row.id}`"
                    @click="onResetRow(row)"
                />
                <Button
                    type="button"
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    rounded
                    aria-label="Remove from inventory"
                    :data-test="`inventory-row-remove-${row.id}`"
                    @click="onRemoveRow(row)"
                />
            </div>
        </template>

        <template #empty>
            <div
                class="flex flex-col items-center gap-3 py-8 text-center text-sm text-muted-foreground"
                data-test="inventory-filtered-empty"
            >
                <p>No inventory matches these filters.</p>
            </div>
        </template>

        <template #mobile-row="{ row, selected, toggleSelect }">
            <div
                class="flex flex-col gap-2 rounded-lg border border-border bg-card p-3"
                :class="selected ? 'ring-2 ring-mf-orange/40' : null"
                :data-test="`inventory-card-${row.id}`"
            >
                <div class="flex items-start justify-between gap-2">
                    <input
                        type="checkbox"
                        :checked="selected"
                        @change="toggleSelect"
                    />
                    <div class="flex flex-1 flex-col">
                        <span class="text-sm font-medium text-foreground">
                            {{ row.product_name }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            #{{ row.number }} · {{ row.condition }}
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                    <span class="text-muted-foreground">Market</span>
                    <MfMoney :cents="row.market_price" align="right" />
                    <span class="text-muted-foreground">Low</span>
                    <MfMoney :cents="row.low_price" align="right" />
                    <span class="text-muted-foreground">Calculated</span>
                    <MfMoney :cents="row.calculated_price" align="right" />
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground">Override</span>
                    <div
                        v-if="!isEditingOverride(row.id)"
                        role="button"
                        tabindex="0"
                        @click="beginEditOverride(row)"
                        @keydown.enter.prevent="beginEditOverride(row)"
                    >
                        <MfMoney :cents="row.override_price" />
                    </div>
                    <MfMoneyInput
                        v-else-if="editing && editing.kind === 'override'"
                        :model-value="editing.value"
                        nullable
                        autofocus
                        @update:model-value="
                            (v) =>
                                editing &&
                                editing.kind === 'override' &&
                                (editing.value = v)
                        "
                        @keydown.enter="commitEdit"
                        @keydown.escape="cancelEdit"
                        @blur="commitEdit"
                    />
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground">Qty</span>
                    <div
                        v-if="!isEditingQty(row.id)"
                        role="button"
                        tabindex="0"
                        class="tabular-nums"
                        @click="beginEditQty(row)"
                        @keydown.enter.prevent="beginEditQty(row)"
                    >
                        {{ row.quantity }}
                    </div>
                    <MfQtyInput
                        v-else-if="editing && editing.kind === 'qty'"
                        :model-value="editing.value"
                        @update:model-value="
                            (v: number) =>
                                editing &&
                                editing.kind === 'qty' &&
                                (editing.value = v)
                        "
                        @keydown.enter="commitEdit"
                        @keydown.escape="cancelEdit"
                        @blur="commitEdit"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        v-if="row.override_price !== null"
                        type="button"
                        icon="pi pi-refresh"
                        label="Reset"
                        severity="secondary"
                        size="small"
                        @click="onResetRow(row)"
                    />
                    <Button
                        type="button"
                        icon="pi pi-trash"
                        label="Remove"
                        severity="danger"
                        size="small"
                        @click="onRemoveRow(row)"
                    />
                </div>
            </div>
        </template>
    </MfTable>
</template>
