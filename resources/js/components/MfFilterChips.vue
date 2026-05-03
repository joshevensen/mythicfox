<script setup lang="ts">
import type { ActiveFilter } from '@/components/MfFilter.types';
import MfFilterChip from '@/components/MfFilterChip.vue';

defineProps<{
    filters: ActiveFilter[];
}>();

defineEmits<{
    (e: 'remove', key: string): void;
    (e: 'clear-all'): void;
}>();
</script>

<template>
    <div
        v-if="filters.length > 0"
        class="flex flex-wrap items-center gap-2"
        data-mf-component="filter-chips"
    >
        <MfFilterChip
            v-for="chip in filters"
            :key="chip.key"
            :label="chip.label"
            :value="chip.display"
            @remove="$emit('remove', chip.key)"
        />
        <button
            type="button"
            class="text-xs font-medium text-muted-foreground hover:text-foreground hover:underline"
            @click="$emit('clear-all')"
        >
            Clear all
        </button>
    </div>
</template>
