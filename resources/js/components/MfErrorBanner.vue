<script setup lang="ts">
defineProps<{
    message: string;
    title?: string;
}>();

defineEmits<{
    (e: 'retry'): void;
}>();
</script>

<template>
    <div
        role="alert"
        class="flex flex-wrap items-center gap-3 rounded-md border border-red-500/40 bg-red-500/10 px-4 py-3 text-red-700 dark:text-red-300"
    >
        <i class="pi pi-exclamation-triangle" />
        <div class="flex flex-1 flex-col">
            <p v-if="title" class="font-medium">{{ title }}</p>
            <p class="text-sm">{{ message }}</p>
        </div>
        <button
            type="button"
            class="rounded-md border border-red-500/40 px-3 py-1 text-sm font-medium hover:bg-red-500/10"
            @click="$emit('retry')"
        >
            Retry
        </button>
    </div>
</template>
