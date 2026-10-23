<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CatalogEmptyState from '@/components/catalog/CatalogEmptyState.vue';
import CatalogPageHeader from '@/components/catalog/CatalogPageHeader.vue';
import MfDate from '@/components/MfDate.vue';
import type { FilterDef } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useTableState } from '@/composables/useTableState';
import { show as productShow } from '@/routes/catalog/products';
import { index as productCardsIndex } from '@/routes/catalog/products/cards';
import { show as setShow } from '@/routes/catalog/products/sets';

type SetRow = {
    id: number;
    name: string;
    cards_count: number;
    cards_synced_at: string | null;
};

const props = defineProps<{
    product: {
        id: number;
        label: string;
        priced_at: string | null;
        is_stale: boolean;
    };
    sets: {
        data: SetRow[];
        meta: {
            total: number;
            current_page: number;
            per_page: number;
        };
    };
}>();

const endpoint = computed(() => productShow(props.product.id).url);

// Inertia reuses this component when switching products, so the endpoint is
// read through a getter on every table navigation.
const tableState = useTableState({
    get endpoint() {
        return endpoint.value;
    },
    filterKeys: ['name'],
    defaultSort: { field: 'name', dir: 'asc' },
    inertiaOnly: ['sets'],
});
const { hasActiveFilters, clearFilters: clearAllFilters } = tableState;

const filters: FilterDef[] = [{ kind: 'text', key: 'name', label: 'Name' }];

const columns: ColumnDef<SetRow>[] = [
    {
        key: 'name',
        label: 'Name',
        sortable: true,
    },
    {
        key: 'cards_count',
        label: 'Cards',
        sortable: true,
        align: 'right',
    },
    {
        key: 'cards_synced_at',
        label: 'Last synced',
        sortable: true,
    },
];

const setUrl = (setId: number): string =>
    setShow({ product: props.product.id, set: setId }).url;
</script>

<template>
    <div>
        <Head :title="product.label" />

        <CatalogPageHeader :title="product.label" :product="product">
            <Link
                :href="productCardsIndex(product.id).url"
                class="rounded-md border border-border px-3 py-1.5 text-sm font-medium text-foreground hover:bg-muted"
                data-test="catalog-all-cards"
            >
                All cards
            </Link>
        </CatalogPageHeader>

        <MfTable
            :columns="columns"
            :rows="sets.data"
            :total="sets.meta.total"
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
                <MfFilterPanel :filters="filters" :endpoint="endpoint" />
            </template>

            <template #cell-name="{ row }">
                <Link
                    :href="setUrl(row.id)"
                    class="font-medium text-foreground hover:text-mf-orange hover:underline"
                    :data-test="`catalog-set-link-${row.id}`"
                >
                    {{ row.name }}
                </Link>
            </template>

            <template #cell-cards_synced_at="{ row }">
                <MfDate
                    v-if="row.cards_synced_at"
                    :value="row.cards_synced_at"
                />
                <span v-else>—</span>
            </template>

            <template #empty>
                <div
                    v-if="hasActiveFilters"
                    class="flex flex-col items-center gap-3 py-8 text-center text-sm text-muted-foreground"
                    data-test="catalog-filtered-empty"
                >
                    <p>No sets match these filters.</p>
                    <button
                        type="button"
                        class="text-mf-orange hover:underline"
                        data-test="catalog-clear-filters"
                        @click="clearAllFilters"
                    >
                        Clear filters
                    </button>
                </div>
                <CatalogEmptyState
                    v-else
                    :title="`No sets for ${product.label} yet.`"
                />
            </template>

            <template #mobile-row="{ row }">
                <div
                    class="flex flex-col gap-1 rounded-lg border border-border bg-card p-3"
                    :data-test="`catalog-set-${row.id}`"
                >
                    <Link
                        :href="setUrl(row.id)"
                        class="text-sm font-medium text-foreground hover:text-mf-orange hover:underline"
                        :data-test="`catalog-set-link-${row.id}`"
                    >
                        {{ row.name }}
                    </Link>
                    <span class="text-xs text-muted-foreground">
                        {{ row.cards_count }} cards
                    </span>
                    <span class="text-xs text-muted-foreground">
                        Last synced
                        <MfDate
                            v-if="row.cards_synced_at"
                            :value="row.cards_synced_at"
                        />
                        <template v-else>—</template>
                    </span>
                </div>
            </template>
        </MfTable>
    </div>
</template>
