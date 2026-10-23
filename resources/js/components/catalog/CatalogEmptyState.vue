<script setup lang="ts">
import Button from 'primevue/button';
import { useGlobalImportModal } from '@/composables/useGlobalImportModal';

withDefaults(defineProps<{ title?: string }>(), {
    title: 'No cards in catalog.',
});

const importModal = useGlobalImportModal();

const onUploadClick = (): void => {
    importModal.open('catalog');
};
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 py-10 text-center"
        data-test="catalog-empty"
    >
        <p class="text-base font-medium text-foreground">
            {{ title }}
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
