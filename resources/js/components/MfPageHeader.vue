<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

type Crumb = {
    label: string;
    route?: string;
};

defineProps<{
    title: string;
    breadcrumbs?: Crumb[];
}>();
</script>

<template>
    <header
        class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex flex-col gap-1">
            <nav
                v-if="breadcrumbs && breadcrumbs.length > 0"
                aria-label="Breadcrumb"
                class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
            >
                <template
                    v-for="(crumb, index) in breadcrumbs"
                    :key="`${crumb.label}-${index}`"
                >
                    <i
                        v-if="index > 0"
                        class="pi pi-chevron-right text-xs text-slate-400"
                    />
                    <Link
                        v-if="crumb.route"
                        :href="crumb.route"
                        class="hover:text-foreground hover:underline"
                    >
                        {{ crumb.label }}
                    </Link>
                    <span v-else aria-current="page">{{ crumb.label }}</span>
                </template>
            </nav>
            <h1
                class="text-2xl font-semibold text-slate-900 dark:text-slate-100"
            >
                {{ title }}
            </h1>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
            <slot />
        </div>
    </header>
</template>
