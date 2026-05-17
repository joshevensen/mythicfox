<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed, ref, watch } from 'vue';
import { store as importAction } from '@/actions/App/Http/Controllers/Orders/OrdersImportController';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import { useMfToast } from '@/composables/useMfToast';
import { useOrdersImportModal } from '@/composables/useOrdersImportModal';

type SlotKey = 'orderlist' | 'shipping_export' | 'pull_sheet' | 'packing_slips';

type SlotDef = {
    key: SlotKey;
    label: string;
    accept: string;
    required: boolean;
    hint: string | null;
};

const SLOTS: SlotDef[] = [
    {
        key: 'orderlist',
        label: 'OrderList',
        accept: '.csv',
        required: true,
        hint: 'Source of truth — required for every import.',
    },
    {
        key: 'shipping_export',
        label: 'ShippingExport',
        accept: '.csv',
        required: false,
        hint: 'Without ShippingExport, addresses and tracking are null.',
    },
    {
        key: 'pull_sheet',
        label: 'PullSheet',
        accept: '.csv',
        required: false,
        hint: 'Without PullSheet, no line items are recorded.',
    },
    {
        key: 'packing_slips',
        label: 'PackingSlips',
        accept: '.pdf',
        required: false,
        hint: 'Without the PDF, line-item prices stay null.',
    },
];

const importModal = useOrdersImportModal();
const { error: toastError } = useMfToast();

const inputRefs = ref<Record<SlotKey, HTMLInputElement | null>>({
    orderlist: null,
    shipping_export: null,
    pull_sheet: null,
    packing_slips: null,
});

const setInputRef = (key: SlotKey) => (el: unknown) => {
    inputRefs.value[key] = el instanceof HTMLInputElement ? el : null;
};

const form = useForm<{
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

const errorMessage = ref<string | null>(null);

const onPick = (key: SlotKey, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form[key] = file;
};

const clearSlot = (key: SlotKey) => {
    form[key] = null;
    const ref = inputRefs.value[key];

    if (ref) {
        ref.value = '';
    }
};

const canSubmit = computed(() => form.orderlist !== null && !form.processing);

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    errorMessage.value = null;
    const action = importAction();

    form.submit(action.method, action.url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            importModal.close();
            // Tell the page to refresh table + meta so import-in-flight flag
            // appears immediately.
            router.reload({ only: ['orders', 'meta'] });
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
                errorMessage.value = fileErrors.join('\n');
            } else {
                toastError("Couldn't queue the import. Try again.");
            }
        },
    });
};

watch(
    () => importModal.visible.value,
    (visible) => {
        if (!visible) {
            errorMessage.value = null;
            form.clearErrors();
        }
    },
);
</script>

<template>
    <Dialog
        v-model:visible="importModal.visible.value"
        modal
        :draggable="false"
        :dismissable-mask="!form.processing"
        header="Import orders"
        class="w-full max-w-2xl"
        data-test="orders-import-modal"
    >
        <MfErrorBanner
            v-if="errorMessage"
            class="mb-4"
            title="Some files couldn't be processed"
            :message="errorMessage"
        />

        <div class="flex flex-col gap-4">
            <div
                v-for="slot in SLOTS"
                :key="slot.key"
                class="flex flex-col gap-1.5"
                :data-test="`import-slot-${slot.key}`"
            >
                <div class="flex items-center justify-between gap-3">
                    <label
                        :for="`import-input-${slot.key}`"
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
                        v-if="form[slot.key]"
                        type="button"
                        class="text-xs text-muted-foreground hover:text-red-500"
                        :aria-label="`Clear ${slot.label}`"
                        :data-test="`import-clear-${slot.key}`"
                        @click="clearSlot(slot.key)"
                    >
                        × Clear
                    </button>
                </div>
                <div
                    class="flex items-center gap-3 rounded-md border border-dashed border-border px-3 py-2"
                >
                    <input
                        :id="`import-input-${slot.key}`"
                        :ref="setInputRef(slot.key)"
                        type="file"
                        :accept="slot.accept"
                        class="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border-0 file:bg-mf-orange/10 file:px-3 file:py-1.5 file:text-mf-orange hover:file:bg-mf-orange/20"
                        :data-test="`import-input-${slot.key}`"
                        @change="(e) => onPick(slot.key, e)"
                    />
                </div>
                <p
                    v-if="form[slot.key]"
                    class="text-xs text-emerald-600 dark:text-emerald-400"
                >
                    Selected: {{ form[slot.key]?.name }}
                </p>
                <p v-else-if="slot.hint" class="text-xs text-muted-foreground">
                    {{ slot.hint }}
                </p>
            </div>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                :disabled="form.processing"
                data-test="import-cancel"
                @click="importModal.close"
            />
            <Button
                type="button"
                :label="form.processing ? 'Uploading…' : 'Import'"
                :icon="
                    form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-upload'
                "
                :disabled="!canSubmit"
                data-test="import-submit"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
