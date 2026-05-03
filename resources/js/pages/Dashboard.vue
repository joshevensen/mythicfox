<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MfPageHeader from '@/components/MfPageHeader.vue';
import { addCards } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import { index as inventoryIndex } from '@/routes/inventory';
import { index as ordersIndex } from '@/routes/orders';

defineProps<{
    firstName: string;
}>();

type Tile = {
    label: string;
    icon: string;
    description: string;
    href: string;
    testId: string;
};

const tiles: Tile[] = [
    {
        label: '+ Add Cards',
        icon: 'pi pi-plus',
        description: 'Add new cards to inventory',
        href: addCards().url,
        testId: 'tile-add-cards',
    },
    {
        label: '⬆ Import Orders',
        icon: 'pi pi-upload',
        description: 'Print packing slips and import a fresh batch',
        href: ordersIndex({ query: { import: 1 } }).url,
        testId: 'tile-import-orders',
    },
    {
        label: '📃 Catalog',
        icon: 'pi pi-list',
        description: 'Browse the card catalog',
        href: catalogIndex().url,
        testId: 'tile-catalog',
    },
    {
        label: '💲 Export Pricing',
        icon: 'pi pi-dollar',
        description: 'Recompute and export prices to TCGPlayer',
        href: inventoryIndex({ query: { export: 1 } }).url,
        testId: 'tile-export-pricing',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <div data-test="dashboard-greeting">
        <MfPageHeader :title="`Welcome back, ${firstName}`" />
        <p class="-mt-4 mb-6 text-sm text-muted-foreground">Mythic Fox Games</p>
    </div>

    <section
        aria-label="Quick actions"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
        data-test="dashboard-tiles"
    >
        <Link
            v-for="tile in tiles"
            :key="tile.label"
            :href="tile.href"
            :data-test="tile.testId"
            class="group flex min-h-30 flex-col gap-2 rounded-xl border border-border bg-card p-5 text-left transition-colors hover:border-mf-orange hover:bg-mf-orange/5 focus-visible:border-mf-orange focus-visible:outline-none"
        >
            <span
                class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-mf-orange/10 text-mf-orange"
            >
                <i :class="`${tile.icon} text-lg`" />
            </span>
            <span
                class="text-base font-semibold text-foreground group-hover:text-mf-orange"
            >
                {{ tile.label }}
            </span>
            <span class="text-sm text-muted-foreground">
                {{ tile.description }}
            </span>
        </Link>
    </section>

    <p class="mt-8 text-sm text-muted-foreground">
        More dashboards coming soon as your workflow takes shape.
    </p>
</template>
