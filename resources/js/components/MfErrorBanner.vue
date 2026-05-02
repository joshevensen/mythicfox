<script setup lang="ts">
type Props = {
    message: string;
    title?: string;
    onRetry?: () => void;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'retry'): void;
    (e: 'dismiss'): void;
}>();

const handleRetry = (): void => {
    if (props.onRetry) {
        props.onRetry();
    }

    emit('retry');
};
</script>

<template>
    <div
        role="alert"
        class="flex flex-wrap items-start gap-3 rounded-md border-l-4 border-red-500 bg-red-500/10 px-4 py-3 text-red-700 dark:text-red-300"
        data-mf-component="error-banner"
    >
        <i class="pi pi-exclamation-triangle mt-0.5" />
        <div class="flex flex-1 flex-col gap-0.5">
            <p v-if="title" class="font-medium">{{ title }}</p>
            <p class="text-sm">{{ message }}</p>
        </div>
        <button
            v-if="onRetry"
            type="button"
            class="rounded-md border border-red-500/40 px-3 py-1 text-sm font-medium hover:bg-red-500/10"
            @click="handleRetry"
        >
            Retry
        </button>
        <button
            v-else
            type="button"
            class="rounded-md border border-red-500/40 px-3 py-1 text-sm font-medium hover:bg-red-500/10"
            @click="$emit('dismiss')"
        >
            Dismiss
        </button>
    </div>
</template>
