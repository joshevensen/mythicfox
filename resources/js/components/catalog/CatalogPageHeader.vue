<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';

type CatalogProduct = {
    id: number;
    label: string;
    priced_at: string | null;
    is_stale: boolean;
};

type Crumb = {
    label: string;
    route?: string;
};

const props = defineProps<{
    title: string;
    product: CatalogProduct;
    breadcrumbs?: Crumb[];
}>();

const page = usePage<{
    global_imports?: { catalog: { upload_error: string | null } };
}>();

const uploadError = computed(
    () => page.props.global_imports?.catalog.upload_error ?? null,
);

const errorBanner = ref<string | null>(uploadError.value);

watch(uploadError, (next) => {
    if (next) {
        errorBanner.value = next;
    }
});

const dismissErrorBanner = (): void => {
    errorBanner.value = null;
};

const dayDiff = (iso: string): number => {
    const then = new Date(iso).getTime();
    const now = Date.now();

    return Math.max(0, Math.floor((now - then) / (24 * 60 * 60 * 1000)));
};

const stalenessLabel = computed((): string => {
    if (!props.product.priced_at) {
        return 'never refreshed';
    }

    const days = dayDiff(props.product.priced_at);

    if (days === 0) {
        return 'refreshed today';
    }

    if (days === 1) {
        return 'refreshed 1 day ago';
    }

    return `refreshed ${days} days ago`;
});
</script>

<template>
    <div>
        <MfPageHeader :title="title" :breadcrumbs="breadcrumbs">
            <span
                :class="[
                    'mr-2 hidden text-xs sm:inline',
                    product.is_stale
                        ? 'text-amber-600 dark:text-amber-400'
                        : 'text-muted-foreground',
                ]"
                data-test="catalog-staleness"
            >
                {{ stalenessLabel }}
            </span>
            <slot />
        </MfPageHeader>

        <MfErrorBanner
            v-if="errorBanner"
            class="mb-4"
            title="Import failed"
            :message="errorBanner"
            @dismiss="dismissErrorBanner"
        />
    </div>
</template>
