<script setup lang="ts" generic="TRow extends Record<string, unknown>">
import { router } from '@inertiajs/vue3';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import type {
    DataTablePageEvent,
    DataTableSortEvent,
} from 'primevue/datatable';
import Paginator from 'primevue/paginator';
import type { PageState } from 'primevue/paginator';
import Skeleton from 'primevue/skeleton';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import MfEmptyState from '@/components/MfEmptyState.vue';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import { PAGE_SIZE_OPTIONS } from '@/components/MfTable.types';
import type {
    ColumnDef,
    RowAction,
    SortState,
} from '@/components/MfTable.types';

type Props = {
    columns: ColumnDef<TRow>[];
    rows: TRow[];
    total: number;
    page: number;
    perPage: number;
    sort: SortState;
    rowKey?: string;
    selectable?: boolean;
    expandable?: boolean;
    rowAction?: RowAction;
    skeletonRows?: number;
    errorMessage?: string;
};

const props = withDefaults(defineProps<Props>(), {
    rowKey: 'id',
    selectable: false,
    expandable: false,
    rowAction: 'none',
    skeletonRows: 5,
    errorMessage: undefined,
});

const emit = defineEmits<{
    (e: 'update:page', value: number): void;
    (e: 'update:perPage', value: number): void;
    (e: 'update:sort', value: SortState): void;
}>();

const loading = ref(false);
const fetchError = ref<string | null>(props.errorMessage ?? null);

const selectedKeys = ref<Set<string | number>>(new Set());
const expandedKeys = ref<Set<string | number>>(new Set());
const selectAllMatching = ref(false);

const rowKeyOf = (row: TRow): string | number => {
    const value = row[props.rowKey] as string | number | undefined;

    return value ?? JSON.stringify(row);
};

const isRowSelected = (row: TRow): boolean =>
    selectedKeys.value.has(rowKeyOf(row));

const toggleSelect = (row: TRow): void => {
    const key = rowKeyOf(row);
    const next = new Set(selectedKeys.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    selectedKeys.value = next;
};

const allOnPageSelected = computed(() => {
    if (props.rows.length === 0) {
        return false;
    }

    return props.rows.every((row) => selectedKeys.value.has(rowKeyOf(row)));
});

const togglePageSelection = (): void => {
    const next = new Set(selectedKeys.value);

    if (allOnPageSelected.value) {
        for (const row of props.rows) {
            next.delete(rowKeyOf(row));
        }

        selectAllMatching.value = false;
    } else {
        for (const row of props.rows) {
            next.add(rowKeyOf(row));
        }
    }

    selectedKeys.value = next;
};

const toggleExpand = (row: TRow): void => {
    const key = rowKeyOf(row);
    const next = new Set(expandedKeys.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expandedKeys.value = next;
};

const isRowExpanded = (row: TRow): boolean =>
    expandedKeys.value.has(rowKeyOf(row));

// PrimeVue DataTable expects expandedRows as an array of row records.
// We mirror the Set into an array so the chevron column and any external
// trigger (e.g. clicking the parent row) keep state in sync.
const expandedRowsArray = computed(() =>
    props.rows.filter((row) => expandedKeys.value.has(rowKeyOf(row))),
);

const onPage = (event: DataTablePageEvent | PageState): void => {
    const nextPage = (event.page ?? 0) + 1;
    const nextPerPage = event.rows ?? props.perPage;

    if (nextPerPage !== props.perPage) {
        emit('update:perPage', nextPerPage);
    }

    if (nextPage !== props.page) {
        emit('update:page', nextPage);
    }
};

const onSort = (event: DataTableSortEvent): void => {
    const field = event.sortField as string | null;
    const order = event.sortOrder ?? 0;

    if (!field || order === 0) {
        emit('update:sort', null);

        return;
    }

    emit('update:sort', {
        field,
        dir: order === 1 ? 'asc' : 'desc',
    });
};

const inertiaStartHandler = (): void => {
    loading.value = true;
};

const inertiaFinishHandler = (): void => {
    loading.value = false;
};

let removeStart: () => void;
let removeFinish: () => void;
onMounted(() => {
    removeStart = router.on('start', inertiaStartHandler);
    removeFinish = router.on('finish', inertiaFinishHandler);
});
onUnmounted(() => {
    removeStart?.();
    removeFinish?.();
});

watch(
    () => props.errorMessage,
    (next) => {
        fetchError.value = next ?? null;
    },
);

const selectionCount = computed(() => selectedKeys.value.size);
const selectedKeysArray = computed(() => Array.from(selectedKeys.value));

const showingFrom = computed(() =>
    props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1,
);
const showingTo = computed(() =>
    Math.min(props.page * props.perPage, props.total),
);

const skeletonPlaceholders = computed(() =>
    Array.from({ length: props.skeletonRows }, (_unused, index) => ({
        __skeleton: true,
        __index: index,
    })),
);

const sortField = computed(() => props.sort?.field ?? null);
const sortOrder = computed(() =>
    props.sort ? (props.sort.dir === 'asc' ? 1 : -1) : null,
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <MfErrorBanner v-if="fetchError" :message="fetchError" />

        <div v-if="$slots.filters">
            <slot name="filters" />
        </div>

        <div
            v-if="selectable && selectionCount > 0"
            class="sticky top-14 z-20 flex flex-wrap items-center gap-3 rounded-md border border-mf-orange/40 bg-mf-orange/10 px-4 py-2"
            data-mf-slot="bulk-actions"
        >
            <span class="text-sm font-medium text-foreground">
                {{ selectionCount }} selected
            </span>
            <button
                v-if="allOnPageSelected && total > rows.length"
                type="button"
                class="text-sm font-medium text-mf-orange hover:underline"
                @click="selectAllMatching = !selectAllMatching"
            >
                {{
                    selectAllMatching
                        ? 'Selected all matching'
                        : `Select all ${total} matching`
                }}
            </button>
            <slot
                name="bulk-actions"
                :selected-count="selectionCount"
                :selected-keys="selectedKeysArray"
                :select-all-matching="selectAllMatching"
            />
        </div>

        <!-- Mobile card list -->
        <div
            v-if="$slots['mobile-row']"
            class="flex flex-col gap-3 md:hidden"
            data-mf-slot="mobile-rows"
        >
            <template v-if="loading">
                <Skeleton
                    v-for="placeholder in skeletonPlaceholders"
                    :key="`m-skel-${placeholder.__index}`"
                    height="5rem"
                />
            </template>
            <template v-else-if="rows.length === 0">
                <slot name="empty">
                    <MfEmptyState title="No results" />
                </slot>
            </template>
            <template v-else>
                <slot
                    v-for="row in rows"
                    name="mobile-row"
                    :key="rowKeyOf(row)"
                    :row="row"
                    :selected="isRowSelected(row)"
                    :toggle-select="() => toggleSelect(row)"
                    :expanded="isRowExpanded(row)"
                    :toggle-expand="() => toggleExpand(row)"
                />
            </template>
        </div>

        <!-- Desktop table (and mobile fallback when no mobile-row slot) -->
        <div
            :class="[
                $slots['mobile-row']
                    ? 'hidden md:block'
                    : 'block overflow-x-auto',
            ]"
        >
            <DataTable
                :value="loading ? skeletonPlaceholders : rows"
                :lazy="true"
                :total-records="total"
                :rows="perPage"
                :first="(page - 1) * perPage"
                :sort-field="sortField ?? undefined"
                :sort-order="sortOrder ?? undefined"
                :data-key="rowKey"
                :expanded-rows="
                    expandable && !loading ? expandedRowsArray : undefined
                "
                paginator-template=""
                :paginator="false"
                striped-rows
                size="small"
                @page="onPage"
                @sort="onSort"
            >
                <template #empty>
                    <slot name="empty">
                        <MfEmptyState title="No results" />
                    </slot>
                </template>

                <Column
                    v-if="selectable"
                    header-style="width: 3rem"
                    body-style="width: 3rem"
                >
                    <template #header>
                        <Checkbox
                            :model-value="allOnPageSelected"
                            binary
                            :disabled="loading || rows.length === 0"
                            @update:model-value="togglePageSelection"
                        />
                    </template>
                    <template #body="{ data }">
                        <Skeleton
                            v-if="loading"
                            width="1.25rem"
                            height="1.25rem"
                        />
                        <Checkbox
                            v-else
                            :model-value="isRowSelected(data)"
                            binary
                            @update:model-value="toggleSelect(data)"
                        />
                    </template>
                </Column>

                <Column
                    v-if="expandable"
                    header-style="width: 3rem"
                    body-style="width: 3rem"
                >
                    <template #body="{ data }">
                        <Skeleton
                            v-if="loading"
                            width="1.25rem"
                            height="1.25rem"
                        />
                        <button
                            v-else
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                            :aria-expanded="isRowExpanded(data)"
                            @click="toggleExpand(data)"
                        >
                            <i
                                :class="[
                                    'pi text-xs',
                                    isRowExpanded(data)
                                        ? 'pi-chevron-down'
                                        : 'pi-chevron-right',
                                ]"
                            />
                        </button>
                    </template>
                </Column>

                <Column
                    v-for="col in columns"
                    :key="col.key"
                    :field="col.key"
                    :header="col.label"
                    :sortable="col.sortable"
                    :body-style="
                        col.align ? `text-align: ${col.align}` : undefined
                    "
                    :header-style="
                        col.align ? `text-align: ${col.align}` : undefined
                    "
                >
                    <template #body="{ data }">
                        <Skeleton v-if="loading" height="1rem" />
                        <template
                            v-else-if="col.slot && $slots[`cell-${col.slot}`]"
                        >
                            <slot
                                :name="`cell-${col.slot}`"
                                :row="data"
                                :value="data[col.key]"
                            />
                        </template>
                        <template v-else-if="$slots[`cell-${col.key}`]">
                            <slot
                                :name="`cell-${col.key}`"
                                :row="data"
                                :value="data[col.key]"
                            />
                        </template>
                        <span v-else>
                            {{
                                col.formatter
                                    ? col.formatter(data[col.key], data)
                                    : data[col.key]
                            }}
                        </span>
                    </template>
                </Column>

                <template
                    v-if="expandable && $slots['expand-row']"
                    #expansion="{ data }"
                >
                    <slot name="expand-row" :row="data" />
                </template>
            </DataTable>
        </div>

        <div
            class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
        >
            <span class="text-sm text-muted-foreground">
                Showing {{ showingFrom }}–{{ showingTo }} of {{ total }}
            </span>
            <div class="flex items-center gap-3">
                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    Per page
                    <select
                        :value="perPage"
                        class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                        @change="
                            (e) =>
                                $emit(
                                    'update:perPage',
                                    Number(
                                        (e.target as HTMLSelectElement).value,
                                    ),
                                )
                        "
                    >
                        <option
                            v-for="size in PAGE_SIZE_OPTIONS"
                            :key="size"
                            :value="size"
                        >
                            {{ size }}
                        </option>
                    </select>
                </label>
                <Paginator
                    :rows="perPage"
                    :total-records="total"
                    :first="(page - 1) * perPage"
                    template="FirstPageLink PrevPageLink JumpToPageInput NextPageLink LastPageLink"
                    @page="onPage"
                />
            </div>
        </div>
    </div>
</template>
