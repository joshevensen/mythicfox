<script setup lang="ts">
defineProps<{
    label: string;
    value: string;
}>();

defineEmits<{
    (e: 'remove'): void;
}>();
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-full border border-mf-orange/40 bg-mf-orange/10 px-3 py-1 text-xs font-medium text-mf-orange hover:bg-mf-orange/15"
        data-mf-component="filter-chip"
        @click="$emit('remove')"
    >
        <span class="text-foreground/80">{{ label }}:</span>
        <span>{{ value }}</span>
        <i class="pi pi-times text-[10px]" />
    </button>
</template>
