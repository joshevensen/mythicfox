<script setup lang="ts">
defineProps<{
    value: string | number;
}>();
</script>

<template>
    <span class="font-mono text-sm whitespace-nowrap">{{ value }}</span>
</template>
