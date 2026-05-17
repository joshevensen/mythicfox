<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { ref, watch } from 'vue';

type Props = {
    modelValue: string;
    placeholder?: string;
    debounceMs?: number;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Search…',
    debounceMs: 300,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const local = ref<string>(props.modelValue);

watch(
    () => props.modelValue,
    (next) => {
        if (next !== local.value) {
            local.value = next;
        }
    },
);

const emitDebounced = useDebounceFn((value: string) => {
    emit('update:modelValue', value);
}, props.debounceMs);

const onInput = (value: string | undefined) => {
    const next = value ?? '';

    local.value = next;
    emitDebounced(next);
};
</script>

<template>
    <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
            :model-value="local"
            :placeholder="placeholder"
            class="w-full"
            @update:model-value="onInput"
        />
    </IconField>
</template>
