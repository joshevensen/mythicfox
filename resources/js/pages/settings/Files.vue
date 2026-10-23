<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FileHistorySection from '@/components/settings/FileHistorySection.vue';
import { index as filesIndex } from '@/routes/settings/files';

type FileRow = {
    id: number;
    type: 'import' | 'export';
    purpose: string;
    original_filename: string;
    uploaded_at: string | null;
    expired_at: string | null;
    is_expired: boolean;
};

type FilesPayload = {
    data: FileRow[];
    meta: { total: number; current_page: number; per_page: number };
};

type PurposeOption = { value: string; label: string };

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'File settings',
                href: filesIndex(),
            },
        ],
    },
});

defineProps<{
    files: FilesPayload;
    filePurposes: PurposeOption[];
}>();
</script>

<template>
    <Head title="File settings" />

    <h1 class="sr-only">File settings</h1>

    <FileHistorySection :files="files" :purposes="filePurposes" />
</template>
