<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    status: string;
    trackingNumber: string | null;
};

const props = defineProps<Props>();

type PillVariant = {
    classes: string;
    icon: string | null;
    label: string;
};

const variant = computed<PillVariant>(() => {
    if (props.status === 'Completed - Paid') {
        if (props.trackingNumber) {
            return {
                classes:
                    'bg-emerald-500 text-white dark:bg-emerald-400 dark:text-slate-900',
                icon: 'pi-check',
                label: 'Shipped',
            };
        }

        return {
            classes:
                'bg-amber-500 text-white dark:bg-amber-400 dark:text-slate-900',
            icon: 'pi-clock',
            label: 'Awaiting shipment',
        };
    }

    if (props.status === 'Canceled') {
        return {
            classes:
                'bg-red-500 text-white dark:bg-red-400 dark:text-slate-900',
            icon: 'pi-times',
            label: 'Canceled',
        };
    }

    return {
        classes:
            'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        icon: null,
        label: props.status,
    };
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
            variant.classes,
        ]"
        data-mf-component="status-pill"
    >
        <i v-if="variant.icon" :class="['pi text-[10px]', variant.icon]" />
        <span>{{ variant.label }}</span>
    </span>
</template>
