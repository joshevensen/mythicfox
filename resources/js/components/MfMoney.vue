<script setup lang="ts">
import { computed } from 'vue';
import { useMoney } from '@/composables/useMoney';

type Props = {
    cents: number | null;
    align?: 'left' | 'right';
};

const props = withDefaults(defineProps<Props>(), {
    align: 'right',
});

const { formatCents } = useMoney();

const formatted = computed(() => formatCents(props.cents));
</script>

<template>
    <span
        :class="['tabular-nums', align === 'right' ? 'text-right' : 'text-left']"
    >
        {{ formatted }}
    </span>
</template>
