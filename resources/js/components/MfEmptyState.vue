<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    icon?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-border px-6 py-10 text-center"
    >
        <i v-if="icon" :class="['pi', icon, 'text-2xl text-muted-foreground']" />
        <p v-if="title" class="text-base font-medium text-foreground">
            {{ title }}
        </p>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
