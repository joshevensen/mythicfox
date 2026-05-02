<script setup lang="ts">
defineProps<{
    value: string | number;
}>();
</script>

<template>
    <span class="whitespace-nowrap font-mono text-sm">{{ value }}</span>
</template>
