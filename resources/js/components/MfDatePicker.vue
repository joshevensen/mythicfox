<script setup lang="ts">
import DatePicker from 'primevue/datepicker';
import { computed } from 'vue';

type Props = {
    modelValue: string | [string | null, string | null] | null;
    range?: boolean;
    inputId?: string;
    placeholder?: string;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    range: false,
    inputId: undefined,
    placeholder: undefined,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | [string | null, string | null] | null): void;
}>();

const toDate = (iso: string | null): Date | null => {
    if (!iso) {
        return null;
    }

    const [y, m, d] = iso.split('-').map(Number);

    if (!y || !m || !d) {
        return null;
    }

    return new Date(y, m - 1, d);
};

const toIso = (date: Date | null | undefined): string | null => {
    if (!date) {
        return null;
    }

    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
};

const internal = computed<Date | (Date | null)[] | null>(() => {
    if (props.range) {
        const value = (props.modelValue as [string | null, string | null] | null) ?? [null, null];

        return [toDate(value[0]), toDate(value[1])];
    }

    return toDate(props.modelValue as string | null);
});

const onChange = (value: Date | (Date | null)[] | null | undefined): void => {
    if (props.range) {
        const list = (value as (Date | null)[] | null) ?? [null, null];

        emit('update:modelValue', [toIso(list[0]), toIso(list[1])]);

        return;
    }

    emit('update:modelValue', toIso(value as Date | null | undefined));
};
</script>

<template>
    <DatePicker
        :model-value="internal"
        :selection-mode="range ? 'range' : 'single'"
        date-format="yy-mm-dd"
        :input-id="inputId"
        :placeholder="placeholder"
        :disabled="disabled"
        @update:model-value="onChange"
    />
</template>
