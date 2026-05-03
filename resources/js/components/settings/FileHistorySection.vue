<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed } from 'vue';
import { download as downloadAction } from '@/actions/App/Http/Controllers/Settings/FilesController';
import MfDate from '@/components/MfDate.vue';
import type { FilterDef } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';
import type { ColumnDef } from '@/components/MfTable.types';
import MfTable from '@/components/MfTable.vue';
import { useMfToast } from '@/composables/useMfToast';
import { useTableState } from '@/composables/useTableState';
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

const { error } = useMfToast();

// `hide_expired` is rendered as a standalone toggle above the table per
// settings.md §Mobile layout — it stays visible on phones where the rest of
// the filter panel collapses into a drawer.
const tableState = useTableState({
    endpoint: settings().url,
    filterKeys: [
        'direction',
        'purpose',
        'uploaded_at_from',
        'uploaded_at_to',
        'hide_expired',
    ],
    defaultSort: { field: 'uploaded_at', dir: 'desc' },
    inertiaOnly: ['files'],
});

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
]);

const columns: ColumnDef<FileRow>[] = [
    { key: 'original_filename', label: 'Filename', sortable: true },
    { key: 'type', label: 'Direction', sortable: true },
    { key: 'purpose', label: 'Purpose', sortable: true },
    { key: 'uploaded_at', label: 'Uploaded', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'actions', label: '' },
];

const hideExpired = computed<boolean>({
    get: () => tableState.filters.value.hide_expired === '1',
    set: (next) => tableState.setFilter('hide_expired', next),
});

const onDownload = async (id: number) => {
    try {
        const response = await fetch(downloadAction(id).url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            error(
                "Couldn't generate download URL — file may be missing from storage.",
            );

            return;
        }

        const data = (await response.json()) as { url?: string };

        if (!data.url) {
            error(
                "Couldn't generate download URL — file may be missing from storage.",
            );

            return;
        }

        window.open(data.url, '_blank', 'noopener');
    } catch {
        error(
            "Couldn't generate download URL — file may be missing from storage.",
        );
    }
};
</script>

<template>
    <section
        id="file-history"
        class="mt-12 scroll-mt-20"
        data-test="file-history-section"
    >
        <h2 class="mb-4 text-xl font-semibold text-foreground">File History</h2>

        <MfTable
            :columns="columns"
            :rows="files.data"
            :total="files.meta.total"
            :page="tableState.page.value"
            :per-page="tableState.perPage.value"
            :sort="tableState.sort.value"
            :skeleton-rows="5"
            @update:page="tableState.setPage"
            @update:per-page="tableState.setPerPage"
            @update:sort="tableState.setSort"
        >
            <template #filters>
                <MfFilterPanel :filters="filters" :endpoint="settings().url" />
                <label
                    class="mt-3 inline-flex items-center gap-2 text-sm text-foreground"
                    data-test="hide-expired-toggle"
                >
                    <ToggleSwitch v-model="hideExpired" />
                    Hide expired
                </label>
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
                <button
                    v-if="!row.is_expired"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                    :data-test="`file-download-${row.id}`"
                    aria-label="Download file"
                    @click="onDownload(row.id)"
                >
                    <i class="pi pi-download" />
                </button>
            </template>

            <template #empty>
                <div
                    v-if="tableState.hasActiveFilters.value"
                    class="flex flex-col items-center gap-3 py-8 text-center text-sm text-muted-foreground"
                    data-test="file-history-filtered-empty"
                >
                    <p>No files match these filters.</p>
                    <Link
                        :href="settings().url"
                        class="text-mf-orange hover:underline"
                        data-test="clear-filters"
                    >
                        Clear filters
                    </Link>
                </div>
                <div
                    v-else
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
                    <button
                        v-if="!row.is_expired"
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-mf-orange"
                        aria-label="Download file"
                        @click="onDownload(row.id)"
                    >
                        <i class="pi pi-download" />
                    </button>
                </div>
            </template>
        </MfTable>
    </section>
</template>
