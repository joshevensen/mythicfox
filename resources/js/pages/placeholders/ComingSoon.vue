<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MfPageHeader from '@/components/MfPageHeader.vue';

defineProps<{
    title: string;
}>();
</script>

<template>
    <Head :title="title" />
    <MfPageHeader :title="title" />
    <p class="text-muted-foreground">Coming soon.</p>
</template>
