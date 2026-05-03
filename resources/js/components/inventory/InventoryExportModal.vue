<script setup lang="ts">
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed, ref, watch } from 'vue';
import MfCardIdentity from '@/components/MfCardIdentity.vue';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import MfMoney from '@/components/MfMoney.vue';
import { useMfToast } from '@/composables/useMfToast';
import {
    download as downloadRoute,
    preview as previewRoute,
    recompute as recomputeRoute,
} from '@/routes/inventory/export';

type DiffRow = {
    id: number;
    product_name: string;
    number: string;
    condition: string;
    set_name: string;
    old_price: number | null;
    new_price: number | null;
    delta: number | null;
};

type PreviewMeta = {
    total: number;
    current_page: number;
    per_page: number;
    changed_count: number;
    total_rows: number;
    first_export: boolean;
};

type PreviewPayload = {
    data: DiffRow[];
    meta: PreviewMeta;
};

const visible = defineModel<boolean>('visible', { default: false });

const emit = defineEmits<{
    (e: 'completed', summary: { rows: number; changed: number }): void;
}>();

const { error: toastError } = useMfToast();

const phase = ref<'idle' | 'recomputing' | 'preview' | 'downloading'>('idle');
const errorMessage = ref<string | null>(null);
const showAll = ref(false);
const page = ref(1);
const previewData = ref<DiffRow[]>([]);
const previewMeta = ref<PreviewMeta | null>(null);

const csrfToken = (): string =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const beginExport = async (): Promise<void> => {
    phase.value = 'recomputing';
    errorMessage.value = null;

    try {
        const response = await fetch(recomputeRoute().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        if (!response.ok) {
            throw new Error(`Recompute failed (${response.status})`);
        }

        await response.json();
        await loadPreview();
        phase.value = 'preview';
    } catch (e) {
        phase.value = 'idle';
        toastError(
            e instanceof Error ? e.message : 'Recompute failed.',
            'Export pricing',
        );
        visible.value = false;
    }
};

const loadPreview = async (): Promise<void> => {
    const url = previewRoute({
        query: {
            page: page.value,
            show_all: showAll.value ? 1 : undefined,
        },
    }).url;

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Preview load failed (${response.status})`);
    }

    const json = (await response.json()) as PreviewPayload;
    previewData.value = json.data;
    previewMeta.value = json.meta;
};

watch(showAll, async () => {
    if (phase.value !== 'preview') {
        return;
    }

    page.value = 1;

    try {
        await loadPreview();
    } catch (e) {
        errorMessage.value = e instanceof Error ? e.message : 'Reload failed.';
    }
});

watch(page, async () => {
    if (phase.value !== 'preview') {
        return;
    }

    try {
        await loadPreview();
    } catch (e) {
        errorMessage.value = e instanceof Error ? e.message : 'Reload failed.';
    }
});

watch(visible, (next) => {
    if (next) {
        page.value = 1;
        showAll.value = false;
        previewData.value = [];
        previewMeta.value = null;
        errorMessage.value = null;
        void beginExport();
    } else {
        phase.value = 'idle';
    }
});

const onDownload = async (): Promise<void> => {
    phase.value = 'downloading';
    errorMessage.value = null;

    try {
        const response = await fetch(downloadRoute().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/csv',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        if (!response.ok) {
            // The server keeps last_exported_price unchanged when the export
            // fails — we just surface the error and let the user retry.
            throw new Error(`Download failed (${response.status})`);
        }

        const rowsHeader = response.headers.get('X-Mf-Rows-Total');
        const changedHeader = response.headers.get('X-Mf-Rows-Changed');

        const blob = await response.blob();

        const disposition = response.headers.get('Content-Disposition') ?? '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match
            ? match[1]
            : `mythic-fox-pricing-${new Date().toISOString().slice(0, 10)}.csv`;

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        const totalRows = rowsHeader
            ? Number(rowsHeader)
            : (previewMeta.value?.total_rows ?? 0);
        const changedRows = changedHeader
            ? Number(changedHeader)
            : (previewMeta.value?.changed_count ?? 0);

        emit('completed', { rows: totalRows, changed: changedRows });

        visible.value = false;
    } catch (e) {
        phase.value = 'preview';
        errorMessage.value =
            e instanceof Error ? e.message : 'Download failed.';
    }
};

const onCancel = (): void => {
    visible.value = false;
};

const onPageEvent = (event: { page?: number }): void => {
    page.value = (event.page ?? 0) + 1;
};

const subtitle = computed(() => {
    const meta = previewMeta.value;

    if (!meta) {
        return '';
    }

    if (meta.changed_count === 0) {
        return 'No price changes since your last export.';
    }

    const noun = meta.changed_count === 1 ? 'row has' : 'rows have';

    return `${meta.changed_count} ${noun} changed effective prices since your last export.`;
});

const formatDelta = (delta: number | null): string => {
    if (delta === null) {
        return '—';
    }

    if (delta === 0) {
        return '$0.00';
    }

    const sign = delta > 0 ? '+' : '−';
    const abs = Math.abs(delta) / 100;

    return `${sign}$${abs.toFixed(2)}`;
};

const deltaClass = (delta: number | null): string => {
    if (delta === null || delta === 0) {
        return 'text-muted-foreground';
    }

    return delta > 0
        ? 'text-emerald-500 dark:text-emerald-400'
        : 'text-red-500 dark:text-red-400';
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        header="Pricing changes"
        :style="{ width: '80vw', maxWidth: '1100px' }"
        :breakpoints="{ '768px': '100vw' }"
        :pt="{
            root: {
                'data-test': 'inventory-export-modal',
                class: 'max-md:!m-0 max-md:!h-screen max-md:!max-h-screen max-md:!rounded-none',
            },
        }"
        @update:visible="(v: boolean) => (visible = v)"
    >
        <div
            v-if="phase === 'recomputing'"
            class="flex items-center gap-3 py-8 text-sm text-muted-foreground"
            data-test="inventory-export-recomputing"
        >
            <i class="pi pi-spin pi-spinner" />
            <span>Recomputing prices…</span>
        </div>

        <div v-else-if="previewMeta" class="flex flex-col gap-4">
            <p
                class="text-sm text-muted-foreground"
                data-test="inventory-export-subtitle"
            >
                {{ subtitle }}
            </p>

            <div
                v-if="previewMeta.first_export"
                class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300"
                data-test="inventory-export-first-banner"
            >
                First export — every row will be set as the new baseline.
            </div>

            <MfErrorBanner
                v-if="errorMessage"
                :message="errorMessage"
                title="Download failed"
            />

            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <ToggleSwitch
                    v-model="showAll"
                    data-test="inventory-export-show-all"
                />
                <span>Show all rows</span>
            </label>

            <DataTable
                :value="previewData"
                :lazy="true"
                :total-records="previewMeta.total"
                :rows="previewMeta.per_page"
                :first="(previewMeta.current_page - 1) * previewMeta.per_page"
                paginator
                paginator-template="PrevPageLink JumpToPageInput NextPageLink"
                size="small"
                striped-rows
                data-key="id"
                data-test="inventory-export-table"
                @page="onPageEvent"
            >
                <Column field="product_name" header="Card">
                    <template #body="{ data }">
                        <MfCardIdentity
                            :card="{
                                name: data.product_name,
                                number: data.number,
                                set_name: data.set_name,
                                condition: data.condition,
                            }"
                        />
                    </template>
                </Column>
                <Column header="Old" body-style="text-align: right">
                    <template #body="{ data }">
                        <MfMoney :cents="data.old_price" />
                    </template>
                </Column>
                <Column header="New" body-style="text-align: right">
                    <template #body="{ data }">
                        <MfMoney :cents="data.new_price" />
                    </template>
                </Column>
                <Column header="Δ" body-style="text-align: right">
                    <template #body="{ data }">
                        <span :class="['tabular-nums', deltaClass(data.delta)]">
                            {{ formatDelta(data.delta) }}
                        </span>
                    </template>
                </Column>
                <template #empty>
                    <div class="py-6 text-center text-sm text-muted-foreground">
                        No rows to display.
                    </div>
                </template>
            </DataTable>
        </div>

        <template #footer>
            <div class="flex justify-between gap-2">
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    outlined
                    :disabled="phase === 'downloading'"
                    data-test="inventory-export-cancel"
                    @click="onCancel"
                />
                <Button
                    type="button"
                    icon="pi pi-download"
                    label="Download CSV"
                    :loading="phase === 'downloading'"
                    :disabled="phase !== 'preview'"
                    data-test="inventory-export-download"
                    @click="onDownload"
                />
            </div>
        </template>
    </Dialog>
</template>
