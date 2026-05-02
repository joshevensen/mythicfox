<script setup lang="ts">
// Usage: pass `:id="name"` to the inner input so the rendered <label for> matches.
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type Props = {
    label: string;
    name: string;
    required?: boolean;
    help?: string;
};

const props = defineProps<Props>();

const page = usePage<{ errors: Record<string, string> }>();

const errorMessage = computed<string | undefined>(
    () => page.props.errors?.[props.name],
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="name" class="text-sm font-medium text-foreground">
            {{ label }}
            <span v-if="required" aria-hidden="true" class="text-red-500"
                >*</span
            >
        </label>
        <slot :error="errorMessage" />
        <p
            v-if="errorMessage"
            class="text-sm text-red-600 dark:text-red-400"
            role="alert"
        >
            {{ errorMessage }}
        </p>
        <p v-else-if="help" class="text-sm text-muted-foreground">
            {{ help }}
        </p>
    </div>
</template>
