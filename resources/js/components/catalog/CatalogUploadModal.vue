<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { ref, watch } from 'vue';
import { store as uploadAction } from '@/actions/App/Http/Controllers/Catalog/CatalogUploadController';
import MfFileDropzone from '@/components/MfFileDropzone.vue';
import { useCatalogUploadModal } from '@/composables/useCatalogUploadModal';
import { useMfToast } from '@/composables/useMfToast';

const uploadModal = useCatalogUploadModal();
const { error: toastError, success } = useMfToast();

const form = useForm<{ file: File | null }>({ file: null });

const dropzoneRef = ref<InstanceType<typeof MfFileDropzone> | null>(null);

const onUpload = (files: File[]): void => {
    const file = files[0] ?? null;

    if (!file) {
        return;
    }

    form.file = file;

    const action = uploadAction();

    form.submit(action.method, action.url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            dropzoneRef.value?.reset();
            uploadModal.close();
            success('PricingCustomExport queued — refreshing catalog…');
            router.reload({ only: ['cards', 'variants', 'meta'] });
        },
        onError: () => {
            dropzoneRef.value?.reset();
            toastError("Couldn't queue the upload. Try again.");
        },
    });
};

watch(
    () => uploadModal.visible.value,
    (visible) => {
        if (!visible) {
            form.clearErrors();
            form.reset();
            dropzoneRef.value?.reset();
        }
    },
);
</script>

<template>
    <Dialog
        v-model:visible="uploadModal.visible.value"
        modal
        :draggable="false"
        :dismissable-mask="!form.processing"
        header="Upload PricingCustomExport"
        class="w-full max-w-2xl"
        data-test="catalog-upload-modal"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm text-muted-foreground">
                Drop your TCGPlayer
                <span class="font-mono">PricingCustomExport.csv</span>
                here. The catalog will refresh in the background.
            </p>
            <MfFileDropzone
                ref="dropzoneRef"
                accept=".csv"
                :max-size="209715200"
                :disabled="form.processing"
                @upload="onUpload"
            />
            <p
                v-if="form.processing"
                class="text-xs text-muted-foreground"
                data-test="catalog-upload-uploading"
            >
                Uploading… don't close this window.
            </p>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                :disabled="form.processing"
                data-test="catalog-upload-cancel"
                @click="uploadModal.close"
            />
        </template>
    </Dialog>
</template>
