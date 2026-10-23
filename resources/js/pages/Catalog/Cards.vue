<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CatalogEmptyState from '@/components/catalog/CatalogEmptyState.vue';
import CatalogPageHeader from '@/components/catalog/CatalogPageHeader.vue';
import CatalogPrintingsTable from '@/components/catalog/CatalogPrintingsTable.vue';
import type { FilterDef, FilterOption } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import MfMoney from '@/components/MfMoney.vue';
import MfMonospaceId from '@/components/MfMonospaceId.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useTableState } from '@/composables/useTableState';
import { show as productShow } from '@/routes/catalog/products';
import {
    index as productCardsIndex,
    show as cardShow,
} from '@/routes/catalog/products/cards';
import { show as setShow } from '@/routes/catalog/products/sets';

type CardRow = {
    key: string;
    id: number;
    set_id: number;
    product_id: number;
    name: string;
    number: string;
    set_name: string;
    rarity: string;
    market_price: number | null;
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
    nm_market_price: number | null;
    nm_low_price: number | null;
    lp_market_price: number | null;
    lp_low_price: number | null;
    mp_market_price: number | null;
    mp_low_price: number | null;
};

const props = defineProps<{
    product: {
        id: number;
        label: string;
        priced_at: string | null;
        is_stale: boolean;
    };
    set: { id: number; name: string } | null;
    heading: string;
    cards: CardsPayload;
    variants: Record<string, Variant[]>;
    meta: { rarities: FilterOption[] };
}>();

const endpoint = computed(() =>
    props.set
        ? setShow({ product: props.product.id, set: props.set.id }).url
        : productCardsIndex(props.product.id).url,
);

// Inertia reuses this component between the product and set views, so the
// endpoint is read through a getter on every table navigation.
const tableState = useTableState({
    get endpoint() {
        return endpoint.value;
    },
    filterKeys: ['rarities'],
    defaultSort: { field: 'name', dir: 'asc' },
    inertiaOnly: ['cards', 'variants'],
});
const { hasActiveFilters, clearFilters: clearAllFilters } = tableState;

const filters = computed<FilterDef[]>(() => [
    {
        kind: 'enum',
        key: 'rarities',
        label: 'Rarity',
        options: props.meta.rarities,
    },
]);

const columns = computed<ColumnDef<CardRow>[]>(() => {
    const all: ColumnDef<CardRow>[] = [
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
        {
            key: 'market_price',
            label: 'Market Price',
            sortable: true,
            align: 'right',
        },
    ];

    return props.set ? all.filter((column) => column.key !== 'set_name') : all;
});

const setUrl = (setId: number): string =>
    setShow({ product: props.product.id, set: setId }).url;

const cardUrl = (cardId: number): string =>
    cardShow({ product: props.product.id, card: cardId }).url;
</script>

<template>
    <div>
        <Head :title="heading" />

        <CatalogPageHeader
            :title="heading"
            :product="product"
            :breadcrumbs="[
                { label: product.label, route: productShow(product.id).url },
            ]"
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
                <MfFilterPanel :filters="filters" :endpoint="endpoint" />
            </template>

            <template #cell-name="{ row }">
                <Link
                    :href="cardUrl(row.id)"
                    class="font-medium text-foreground hover:text-mf-orange hover:underline"
                    :data-test="`catalog-card-link-${row.id}`"
                    @click.stop
                >
                    {{ row.name }}
                </Link>
            </template>

            <template #cell-number="{ row }">
                <MfMonospaceId :value="row.number" />
            </template>

            <template #cell-set_name="{ row }">
                <Link
                    :href="setUrl(row.set_id)"
                    class="hover:text-mf-orange hover:underline"
                    @click.stop
                >
                    {{ row.set_name }}
                </Link>
            </template>

            <template #cell-market_price="{ row }">
                <MfMoney
                    v-if="row.market_price !== null"
                    :cents="row.market_price"
                />
            </template>

            <template #expand-row="{ row }">
                <CatalogPrintingsTable :variants="variants[row.key] ?? []" />
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
                <CatalogEmptyState v-else />
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
                            <Link
                                :href="cardUrl(row.id)"
                                class="text-sm font-medium text-foreground hover:text-mf-orange hover:underline"
                                :data-test="`catalog-card-link-${row.id}`"
                                @click.stop
                                @keydown.enter.stop
                                @keydown.space.stop
                            >
                                {{ row.name }}
                            </Link>
                            <span
                                class="text-xs text-muted-foreground"
                                data-test="catalog-card-meta"
                            >
                                #{{ row.number
                                }}<template v-if="!set">
                                    · {{ row.set_name }}</template
                                >
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ row.rarity }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="row.market_price !== null"
                                class="text-sm text-foreground"
                                data-test="catalog-card-market-price"
                            >
                                <MfMoney :cents="row.market_price" />
                            </span>
                            <i
                                :class="[
                                    'pi text-xs text-muted-foreground',
                                    expanded
                                        ? 'pi-chevron-down'
                                        : 'pi-chevron-right',
                                ]"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                    <div v-if="expanded" class="-mx-3 mt-1 -mb-3">
                        <CatalogPrintingsTable
                            :variants="variants[row.key] ?? []"
                        />
                    </div>
                </div>
            </template>
        </MfTable>
    </div>
</template>
