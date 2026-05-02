<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed } from 'vue';

type Props = {
    title: string;
    body?: string;
    ctaLabel?: string;
    ctaRoute?: string;
    icon?: string;
};

const props = defineProps<Props>();

const iconClass = computed(() => (props.icon ? `pi pi-${props.icon}` : null));
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center"
        data-mf-component="empty-state"
    >
        <i v-if="iconClass" :class="[iconClass, 'text-2xl text-slate-400 dark:text-slate-500']" />
        <p class="text-base font-medium text-slate-900 dark:text-slate-100">{{ title }}</p>
        <p v-if="body" class="max-w-md text-sm text-muted-foreground">{{ body }}</p>
        <Link v-if="ctaLabel && ctaRoute" :href="ctaRoute">
            <Button :label="ctaLabel" />
        </Link>
        <slot />
    </div>
</template>
