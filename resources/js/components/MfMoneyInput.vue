<script setup lang="ts">
import InputNumber from 'primevue/inputnumber';
import { computed } from 'vue';

type Props = {
    modelValue: number | null;
    min?: number;
    max?: number;
    nullable?: boolean;
    inputId?: string;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    min: undefined,
    max: undefined,
    nullable: false,
    inputId: undefined,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const dollars = computed<number | null>(() => {
    if (props.modelValue === null || props.modelValue === undefined) {
        return props.nullable ? null : 0;
    }

    return props.modelValue / 100;
});

const onChange = (next: number | null | undefined): void => {
    if (next === null || next === undefined) {
        emit('update:modelValue', props.nullable ? null : 0);

        return;
    }

    emit('update:modelValue', Math.round(next * 100));
};

const dollarMin = computed(() => (props.min === undefined ? undefined : props.min / 100));
const dollarMax = computed(() => (props.max === undefined ? undefined : props.max / 100));
</script>

<template>
    <InputNumber
        :model-value="dollars"
        mode="currency"
        currency="USD"
        locale="en-US"
        :min-fraction-digits="2"
        :max-fraction-digits="2"
        :min="dollarMin"
        :max="dollarMax"
        :input-id="inputId"
        :disabled="disabled"
        @update:model-value="onChange"
    />
</template>
