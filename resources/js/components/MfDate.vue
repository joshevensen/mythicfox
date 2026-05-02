<script setup lang="ts">
import { computed } from 'vue';
import type { DateFormat } from '@/composables/useDate';
import { useDate } from '@/composables/useDate';

type Props = {
    value: string;
    format?: DateFormat;
};

const props = withDefaults(defineProps<Props>(), {
    format: 'date',
});

const { formatDate } = useDate();

const formatted = computed(() => formatDate(props.value, props.format));
</script>

<template>
    <span>{{ formatted }}</span>
</template>
