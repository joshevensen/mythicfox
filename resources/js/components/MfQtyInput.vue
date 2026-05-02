<script setup lang="ts">
import InputNumber from 'primevue/inputnumber';

type Props = {
    modelValue: number;
    min?: number;
    max?: number;
    inputId?: string;
    disabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    min: 0,
    max: undefined,
    inputId: undefined,
    disabled: false,
});

defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();
</script>

<template>
    <InputNumber
        :model-value="modelValue"
        :use-grouping="false"
        :show-buttons="true"
        button-layout="horizontal"
        :min="min"
        :max="max"
        :input-id="inputId"
        :disabled="disabled"
        decrement-button-class="min-h-[44px] min-w-[44px]"
        increment-button-class="min-h-[44px] min-w-[44px]"
        @update:model-value="$emit('update:modelValue', $event ?? 0)"
    >
        <template #incrementbuttonicon>
            <i class="pi pi-plus" />
        </template>
        <template #decrementbuttonicon>
            <i class="pi pi-minus" />
        </template>
    </InputNumber>
</template>
