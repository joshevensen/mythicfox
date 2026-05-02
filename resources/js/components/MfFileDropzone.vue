<script setup lang="ts">
import ProgressBar from 'primevue/progressbar';
import { computed, ref, useTemplateRef } from 'vue';

type ErrorCode = 'invalid-type' | 'too-large' | 'multiple-not-allowed';
type DropzoneError = { code: ErrorCode; message: string };

type State = 'idle' | 'uploading' | 'success' | 'error';

type Props = {
    accept: string;
    multiple?: boolean;
    maxSize?: number;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    maxSize: 209_715_200,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'upload', files: File[]): void;
    (e: 'progress', pct: number): void;
    (e: 'error', err: DropzoneError): void;
}>();

const state = ref<State>('idle');
const dragActive = ref(false);
const progress = ref<number>(0);
const errorMessage = ref<string | null>(null);
const acceptedNames = ref<string[]>([]);
const inputRef = useTemplateRef<HTMLInputElement>('inputRef');

const allowedExtensions = computed<string[]>(() =>
    props.accept
        .split(',')
        .map((s) => s.trim().toLowerCase())
        .filter((s) => s.length > 0),
);

const setProgress = (pct: number): void => {
    progress.value = Math.max(0, Math.min(100, Math.round(pct)));

    if (state.value !== 'uploading' && progress.value > 0 && progress.value < 100) {
        state.value = 'uploading';
    }

    if (progress.value >= 100) {
        state.value = 'success';
    }

    emit('progress', progress.value);
};

const reset = (): void => {
    state.value = 'idle';
    progress.value = 0;
    errorMessage.value = null;
    acceptedNames.value = [];
};

defineExpose({ setProgress, reset });

const reportError = (err: DropzoneError): void => {
    errorMessage.value = err.message;
    state.value = 'error';
    emit('error', err);
};

const fileExt = (name: string): string => {
    const idx = name.lastIndexOf('.');

    return idx === -1 ? '' : name.slice(idx).toLowerCase();
};

const handleFiles = (incoming: FileList | File[]): void => {
    const files = Array.from(incoming);

    if (files.length === 0) {
        return;
    }

    if (!props.multiple && files.length > 1) {
        reportError({
            code: 'multiple-not-allowed',
            message: 'Only one file can be uploaded at a time.',
        });

        return;
    }

    const valid: File[] = [];

    for (const file of files) {
        if (!allowedExtensions.value.includes(fileExt(file.name))) {
            reportError({
                code: 'invalid-type',
                message: `${file.name} has an unsupported file type. Allowed: ${props.accept}.`,
            });

            return;
        }

        if (file.size > props.maxSize) {
            reportError({
                code: 'too-large',
                message: `${file.name} is larger than the ${Math.round(props.maxSize / 1024 / 1024)}MB limit.`,
            });

            return;
        }

        valid.push(file);
    }

    acceptedNames.value = valid.map((f) => f.name);
    state.value = 'uploading';
    progress.value = 0;
    errorMessage.value = null;
    emit('upload', valid);
};

const onDrop = (event: DragEvent): void => {
    event.preventDefault();
    dragActive.value = false;

    if (props.disabled) {
        return;
    }

    if (event.dataTransfer?.files) {
        handleFiles(event.dataTransfer.files);
    }
};

const onDragOver = (event: DragEvent): void => {
    event.preventDefault();

    if (!props.disabled) {
        dragActive.value = true;
    }
};

const onDragLeave = (): void => {
    dragActive.value = false;
};

const onClick = (): void => {
    if (props.disabled) {
        return;
    }

    inputRef.value?.click();
};

const onInputChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;

    if (input.files) {
        handleFiles(input.files);
        input.value = '';
    }
};

const containerClasses = computed(() => {
    const base = [
        'flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors',
        'min-h-[160px] w-full cursor-pointer',
    ];

    if (props.disabled) {
        return [...base, 'cursor-not-allowed border-border bg-muted/30 opacity-60'];
    }

    if (state.value === 'error') {
        return [...base, 'border-red-500 bg-red-500/10'];
    }

    if (state.value === 'success') {
        return [...base, 'border-emerald-500 bg-emerald-500/10'];
    }

    if (dragActive.value) {
        return [...base, 'border-mf-orange bg-mf-orange/10'];
    }

    return [...base, 'border-border hover:border-mf-orange/60 hover:bg-muted/40'];
});
</script>

<template>
    <div
        :class="containerClasses"
        role="button"
        tabindex="0"
        data-mf-component="file-dropzone"
        @click="onClick"
        @keydown.enter.prevent="onClick"
        @keydown.space.prevent="onClick"
        @drop="onDrop"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
    >
        <input
            ref="inputRef"
            type="file"
            class="hidden"
            :accept="accept"
            :multiple="multiple"
            :disabled="disabled"
            @change="onInputChange"
        />

        <template v-if="state === 'idle'">
            <i class="pi pi-cloud-upload text-3xl text-muted-foreground" />
            <p class="text-sm text-foreground">Drop files here or click to browse</p>
            <p class="text-xs text-muted-foreground">
                Accepts {{ accept }} · up to {{ Math.round(maxSize / 1024 / 1024) }}MB
            </p>
        </template>

        <template v-else-if="state === 'uploading'">
            <i class="pi pi-spin pi-spinner text-2xl text-mf-orange" />
            <p class="text-sm text-foreground">Uploading {{ acceptedNames.join(', ') }}</p>
            <ProgressBar :value="progress" class="w-full" />
        </template>

        <template v-else-if="state === 'success'">
            <i class="pi pi-check-circle text-2xl text-emerald-500" />
            <p class="text-sm font-medium text-foreground">Uploaded {{ acceptedNames.join(', ') }}</p>
        </template>

        <template v-else-if="state === 'error'">
            <i class="pi pi-exclamation-triangle text-2xl text-red-500" />
            <p class="text-sm font-medium text-red-600 dark:text-red-300">{{ errorMessage }}</p>
        </template>
    </div>
</template>
