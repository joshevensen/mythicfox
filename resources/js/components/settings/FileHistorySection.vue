<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { download as downloadAction } from '@/actions/App/Http/Controllers/Settings/FilesController';
import MfDate from '@/components/MfDate.vue';
import type { FilterDef } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { settings } from '@/routes';

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
    meta: {
        total: number;
        current_page: number;
        per_page: number;
    };
};

type PurposeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    files: FilesPayload;
    purposes: PurposeOption[];
}>();

const filters = computed<FilterDef[]>(() => [
    {
        kind: 'enum',
        key: 'direction',
        label: 'Direction',
        options: [
            { value: 'import', label: 'Import' },
            { value: 'export', label: 'Export' },
        ],
    },
    {
        kind: 'enum',
        key: 'purpose',
        label: 'Purpose',
        options: props.purposes,
    },
    {
        kind: 'date',
        key: 'uploaded_at',
        label: 'Date range',
    },
    {
        kind: 'boolean',
        key: 'hide_expired',
        label: 'Hide expired',
    },
]);

const columns: ColumnDef<FileRow>[] = [
    { key: 'original_filename', label: 'Filename', sortable: true },
    { key: 'type', label: 'Direction', sortable: true },
    { key: 'purpose', label: 'Purpose', sortable: true },
    { key: 'uploaded_at', label: 'Uploaded', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'actions', label: '' },
];

const downloadUrl = (id: number) => downloadAction(id).url;
</script>

<template>
    <section
        id="file-history"
        class="mt-12 scroll-mt-20"
        data-test="file-history-section"
    >
        <h2 class="mb-4 text-xl font-semibold text-foreground">File history</h2>

        <MfTable
            :endpoint="settings().url"
            :columns="columns"
            :rows="files.data"
            :total="files.meta.total"
            :default-sort="{ column: 'uploaded_at', dir: 'desc' }"
            :inertia-only="['files']"
            :skeleton-rows="5"
        >
            <template #filters>
                <MfFilterPanel :filters="filters" :endpoint="settings().url" />
            </template>

            <template #cell-uploaded_at="{ row }">
                <MfDate
                    v-if="row.uploaded_at"
                    :value="row.uploaded_at"
                    format="datetime"
                />
            </template>

            <template #cell-type="{ row }">
                <span class="capitalize">{{ row.type }}</span>
            </template>

            <template #cell-status="{ row }">
                <span
                    v-if="!row.is_expired"
                    class="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                >
                    Active
                </span>
                <span v-else class="text-muted-foreground">
                    Expired
                    <MfDate
                        v-if="row.expired_at"
                        :value="row.expired_at"
                        format="date"
                    />
                </span>
            </template>

            <template #cell-actions="{ row }">
                <a
                    v-if="!row.is_expired"
                    :href="downloadUrl(row.id)"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                    :data-test="`file-download-${row.id}`"
                    aria-label="Download file"
                >
                    <i class="pi pi-download" />
                </a>
            </template>

            <template #empty>
                <div
                    class="py-8 text-center text-sm text-muted-foreground"
                    data-test="file-history-empty"
                >
                    <p>No files yet — imports and exports will appear here.</p>
                </div>
            </template>

            <template #mobile-row="{ row }">
                <div
                    class="flex items-center justify-between rounded-lg border border-border bg-card p-3"
                >
                    <div class="flex flex-col gap-1 text-sm">
                        <span class="font-medium text-foreground">
                            {{ row.original_filename }}
                        </span>
                        <span class="text-muted-foreground">
                            {{ row.type }} · {{ row.purpose }}
                        </span>
                        <MfDate
                            v-if="row.uploaded_at"
                            :value="row.uploaded_at"
                            format="datetime"
                            class="text-xs text-muted-foreground"
                        />
                        <span
                            v-if="!row.is_expired"
                            class="inline-flex items-center self-start rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            Active
                        </span>
                        <span v-else class="text-xs text-muted-foreground">
                            Expired
                            <MfDate
                                v-if="row.expired_at"
                                :value="row.expired_at"
                                format="date"
                            />
                        </span>
                    </div>
                    <a
                        v-if="!row.is_expired"
                        :href="downloadUrl(row.id)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-9 w-9 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                        aria-label="Download file"
                    >
                        <i class="pi pi-download" />
                    </a>
                </div>
            </template>
        </MfTable>

        <div
            v-if="files.meta.total === 0"
            class="mt-2 text-center text-sm text-muted-foreground"
        >
            <Link :href="settings().url" class="text-mf-orange hover:underline">
                Clear filters
            </Link>
        </div>
    </section>
</template>
