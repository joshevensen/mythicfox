<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed, ref, watch } from 'vue';
import { store as uploadCatalog } from '@/actions/App/Http/Controllers/Catalog/CatalogUploadController';
import { store as importOrders } from '@/actions/App/Http/Controllers/Orders/OrdersImportController';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import MfFileDropzone from '@/components/MfFileDropzone.vue';
import { useGlobalImportModal } from '@/composables/useGlobalImportModal';
import { useMfToast } from '@/composables/useMfToast';

type SlotKey = 'orderlist' | 'shipping_export' | 'pull_sheet' | 'packing_slips';

type SlotDef = {
    key: SlotKey;
    label: string;
    accept: string;
    required: boolean;
    hint: string | null;
};

type GlobalImports = {
    catalog: {
        in_flight: boolean;
    };
    orders: {
        in_flight: boolean;
    };
};

const ORDER_SLOTS: SlotDef[] = [
    {
        key: 'pull_sheet',
        label: 'Pull Sheet',
        accept: '.csv',
        required: false,
        hint: 'Adds order line items when available.',
    },
    {
        key: 'packing_slips',
        label: 'Packing Slips',
        accept: '.pdf',
        required: false,
        hint: 'Adds line-item prices when available.',
    },
    {
        key: 'orderlist',
        label: 'Order List',
        accept: '.csv',
        required: true,
        hint: 'Source of truth for every order import.',
    },
    {
        key: 'shipping_export',
        label: 'Shipping Export',
        accept: '.csv',
        required: false,
        hint: 'Adds addresses and tracking when available.',
    },
];

const importModal = useGlobalImportModal();
const page = usePage<{ global_imports?: GlobalImports }>();
const { error: toastError, success } = useMfToast();

const imports = computed(
    () =>
        page.props.global_imports ?? {
            catalog: { in_flight: false },
            orders: { in_flight: false },
        },
);

const catalogForm = useForm<{ file: File | null }>({ file: null });
const ordersForm = useForm<{
    orderlist: File | null;
    shipping_export: File | null;
    pull_sheet: File | null;
    packing_slips: File | null;
}>({
    orderlist: null,
    shipping_export: null,
    pull_sheet: null,
    packing_slips: null,
});

const catalogDropzoneRef = ref<InstanceType<typeof MfFileDropzone> | null>(
    null,
);
const orderInputRefs = ref<Record<SlotKey, HTMLInputElement | null>>({
    orderlist: null,
    shipping_export: null,
    pull_sheet: null,
    packing_slips: null,
});
const orderErrorMessage = ref<string | null>(null);

const processing = computed(
    () => catalogForm.processing || ordersForm.processing,
);
const canUploadCatalog = computed(
    () => !catalogForm.processing && !imports.value.catalog.in_flight,
);
const canSubmitOrders = computed(
    () =>
        ordersForm.orderlist !== null &&
        !ordersForm.processing &&
        !imports.value.orders.in_flight,
);

const setOrderInputRef = (key: SlotKey) => (el: unknown) => {
    orderInputRefs.value[key] = el instanceof HTMLInputElement ? el : null;
};

const resetCatalog = (): void => {
    catalogForm.clearErrors();
    catalogForm.reset();
    catalogDropzoneRef.value?.reset();
};

const resetOrders = (): void => {
    orderErrorMessage.value = null;
    ordersForm.clearErrors();
    ordersForm.reset();

    for (const ref of Object.values(orderInputRefs.value)) {
        if (ref) {
            ref.value = '';
        }
    }
};

const refreshGlobalImportStatus = (): void => {
    router.reload({
        only: ['global_imports'],
    });
};

const onCatalogUpload = (files: File[]): void => {
    const file = files[0] ?? null;

    if (!file || imports.value.catalog.in_flight) {
        return;
    }

    catalogForm.file = file;
    const action = uploadCatalog();

    catalogForm.submit(action.method, action.url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetCatalog();
            importModal.close();
            success('PricingCustomExport queued — refreshing catalog…');
            refreshGlobalImportStatus();
        },
        onError: () => {
            catalogDropzoneRef.value?.reset();
            toastError("Couldn't queue the catalog upload. Try again.");
        },
    });
};

const onPickOrderFile = (key: SlotKey, event: Event): void => {
    const input = event.target as HTMLInputElement;
    ordersForm[key] = input.files?.[0] ?? null;
};

const clearOrderSlot = (key: SlotKey): void => {
    ordersForm[key] = null;
    const ref = orderInputRefs.value[key];

    if (ref) {
        ref.value = '';
    }
};

const submitOrders = (): void => {
    if (!canSubmitOrders.value) {
        return;
    }

    orderErrorMessage.value = null;
    const action = importOrders();

    ordersForm.submit(action.method, action.url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetOrders();
            importModal.close();
            success('Import queued — processing orders…');
            refreshGlobalImportStatus();
        },
        onError: (errors) => {
            const fileErrors = Object.entries(errors)
                .filter(([key]) =>
                    [
                        'orderlist',
                        'shipping_export',
                        'pull_sheet',
                        'packing_slips',
                    ].includes(key),
                )
                .map(([key, msg]) => `${key}: ${msg}`);

            if (fileErrors.length > 0) {
                orderErrorMessage.value = fileErrors.join('\n');
            } else {
                toastError("Couldn't queue the order import. Try again.");
            }
        },
    });
};

watch(
    () => importModal.visible.value,
    (visible) => {
        if (!visible) {
            resetCatalog();
            resetOrders();
        }
    },
);
</script>

<template>
    <Dialog
        v-model:visible="importModal.visible.value"
        modal
        :draggable="false"
        :dismissable-mask="!processing"
        header="Import files"
        class="w-full max-w-2xl"
        data-test="global-import-modal"
    >
        <div class="flex flex-col gap-5">
            <div
                class="grid grid-cols-2 rounded-md border border-border bg-muted/40 p-1"
                role="tablist"
                aria-label="Import type"
            >
                <button
                    type="button"
                    :class="[
                        'h-10 rounded-sm text-sm font-medium transition-colors',
                        importModal.activeTab.value === 'orders'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    role="tab"
                    data-test="global-import-tab-orders"
                    :aria-selected="importModal.activeTab.value === 'orders'"
                    @click="importModal.activeTab.value = 'orders'"
                >
                    Orders
                </button>
                <button
                    type="button"
                    :class="[
                        'h-10 rounded-sm text-sm font-medium transition-colors',
                        importModal.activeTab.value === 'catalog'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    role="tab"
                    data-test="global-import-tab-catalog"
                    :aria-selected="importModal.activeTab.value === 'catalog'"
                    @click="importModal.activeTab.value = 'catalog'"
                >
                    Catalog
                </button>
            </div>

            <section
                v-if="importModal.activeTab.value === 'catalog'"
                class="flex flex-col gap-4"
                data-test="global-import-catalog-panel"
            >
                <p class="text-sm text-muted-foreground">
                    Drop your TCGPlayer
                    <span class="font-mono">PricingCustomExport.csv</span>
                    here. The catalog refreshes in the background.
                </p>
                <MfFileDropzone
                    ref="catalogDropzoneRef"
                    accept=".csv"
                    :max-size="209715200"
                    :disabled="!canUploadCatalog"
                    @upload="onCatalogUpload"
                />
                <p
                    v-if="catalogForm.processing"
                    class="text-xs text-muted-foreground"
                    data-test="global-import-catalog-uploading"
                >
                    Uploading... do not close this window.
                </p>
                <p
                    v-else-if="imports.catalog.in_flight"
                    class="text-xs text-muted-foreground"
                    data-test="global-import-catalog-in-flight"
                >
                    A catalog import is already running.
                </p>
            </section>

            <section
                v-else
                class="flex flex-col gap-4"
                data-test="global-import-orders-panel"
            >
                <MfErrorBanner
                    v-if="orderErrorMessage"
                    title="Some files couldn't be processed"
                    :message="orderErrorMessage"
                />

                <div
                    v-for="slot in ORDER_SLOTS"
                    :key="slot.key"
                    class="flex flex-col gap-1.5"
                    :data-test="`global-import-slot-${slot.key}`"
                >
                    <div class="flex items-center justify-between gap-3">
                        <label
                            :for="`global-import-input-${slot.key}`"
                            class="text-sm font-medium text-foreground"
                        >
                            {{ slot.label }}
                            <span
                                v-if="slot.required"
                                class="text-mf-orange"
                                aria-hidden="true"
                            >
                                *
                            </span>
                            <span class="ml-1 text-xs text-muted-foreground">
                                ({{ slot.accept }})
                            </span>
                        </label>
                        <button
                            v-if="ordersForm[slot.key]"
                            type="button"
                            class="text-xs text-muted-foreground hover:text-red-500"
                            :aria-label="`Clear ${slot.label}`"
                            :data-test="`global-import-clear-${slot.key}`"
                            @click="clearOrderSlot(slot.key)"
                        >
                            Clear
                        </button>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-md border border-dashed border-border px-3 py-2"
                    >
                        <input
                            :id="`global-import-input-${slot.key}`"
                            :ref="setOrderInputRef(slot.key)"
                            type="file"
                            :accept="slot.accept"
                            :disabled="imports.orders.in_flight"
                            class="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border-0 file:bg-mf-orange/10 file:px-3 file:py-1.5 file:text-mf-orange hover:file:bg-mf-orange/20"
                            :data-test="`global-import-input-${slot.key}`"
                            @change="(e) => onPickOrderFile(slot.key, e)"
                        />
                    </div>
                    <p
                        v-if="ordersForm[slot.key]"
                        class="text-xs text-emerald-600 dark:text-emerald-400"
                    >
                        Selected: {{ ordersForm[slot.key]?.name }}
                    </p>
                    <p
                        v-else-if="
                            slot.key === 'orderlist' && imports.orders.in_flight
                        "
                        class="text-xs text-muted-foreground"
                        data-test="global-import-orders-in-flight"
                    >
                        An order import is already running.
                    </p>
                    <p
                        v-else-if="slot.hint"
                        class="text-xs text-muted-foreground"
                    >
                        {{ slot.hint }}
                    </p>
                </div>
            </section>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                :disabled="processing"
                data-test="global-import-cancel"
                @click="importModal.close"
            />
            <Button
                v-if="importModal.activeTab.value === 'orders'"
                type="button"
                :label="ordersForm.processing ? 'Uploading...' : 'Import'"
                :icon="
                    ordersForm.processing
                        ? 'pi pi-spin pi-spinner'
                        : 'pi pi-upload'
                "
                :disabled="!canSubmitOrders"
                data-test="global-import-orders-submit"
                @click="submitOrders"
            />
        </template>
    </Dialog>
</template>
