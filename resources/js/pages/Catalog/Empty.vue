<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CatalogEmptyState from '@/components/catalog/CatalogEmptyState.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
</script>

<template>
    <div>
        <Head title="Catalog" />

        <MfPageHeader title="Catalog" />

        <CatalogEmptyState />
    </div>
</template>
