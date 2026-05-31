<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import GlobalImportModal from '@/components/GlobalImportModal.vue';
import MfConfirmDialog from '@/components/MfConfirmDialog.vue';
import MfPageContainer from '@/components/MfPageContainer.vue';
import MfToast from '@/components/MfToast.vue';
import MfTopNav from '@/components/MfTopNav.vue';
import { useMfToast } from '@/composables/useMfToast';

type CatalogImportResult = {
    success: boolean;
    rows_processed?: number;
    product_label?: string;
    message?: string;
    completed_at: string;
};

type OrdersImportResult =
    | {
          success: true;
          orders_inserted: number;
          orders_updated: number;
          line_items_unmatched_to_inventory: number;
          completed_at: string;
      }
    | {
          success: false;
          message?: string;
          completed_at: string;
      };

type GlobalImports = {
    catalog: {
        in_flight: boolean;
        last_result: CatalogImportResult | null;
        upload_error: string | null;
    };
    orders: {
        in_flight: boolean;
        last_result: OrdersImportResult | null;
    };
};

const emptyImports = (): GlobalImports => ({
    catalog: {
        in_flight: false,
        last_result: null,
        upload_error: null,
    },
    orders: {
        in_flight: false,
        last_result: null,
    },
});

const page = usePage<{ global_imports?: GlobalImports }>();
const { success, error } = useMfToast();

const imports = computed(() => page.props.global_imports ?? emptyImports());
const pollHandle = ref<number | null>(null);
const wasCatalogInFlight = ref(imports.value.catalog.in_flight);
const wasOrdersInFlight = ref(imports.value.orders.in_flight);
const lastCatalogError = ref<string | null>(imports.value.catalog.upload_error);

const stopPolling = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    if (pollHandle.value !== null) {
        window.clearInterval(pollHandle.value);
        pollHandle.value = null;
    }
};

const startPolling = (): void => {
    if (typeof window === 'undefined' || pollHandle.value !== null) {
        return;
    }

    pollHandle.value = window.setInterval(() => {
        router.reload({
            only: ['global_imports'],
        });
    }, 2000);
};

const reloadCurrentImportPage = (kind: 'catalog' | 'orders'): void => {
    if (kind === 'catalog' && page.url.startsWith('/cards')) {
        router.reload({ only: ['cards', 'variants', 'meta'] });
    }

    if (kind === 'orders' && page.url.startsWith('/orders')) {
        router.reload({ only: ['orders', 'meta'] });
    }
};

const catalogSuccessMessage = (last: CatalogImportResult): string => {
    const rows = last.rows_processed ?? 0;
    const label = last.product_label ?? 'the catalog';

    return `Refreshed ${rows} cards across ${label}.`;
};

const ordersSuccessMessage = (
    last: OrdersImportResult & { success: true },
): string => {
    const total = last.orders_inserted + last.orders_updated;
    const orderWord = total === 1 ? 'order' : 'orders';
    let message = `Imported ${total} ${orderWord} (${last.orders_inserted} new, ${last.orders_updated} updated).`;

    if (last.line_items_unmatched_to_inventory > 0) {
        const itemWord =
            last.line_items_unmatched_to_inventory === 1 ? 'item' : 'items';
        message += ` ${last.line_items_unmatched_to_inventory} ${itemWord} could not be matched to inventory.`;
    }

    return message;
};

watch(
    imports,
    (next) => {
        if (
            next.catalog.upload_error &&
            next.catalog.upload_error !== lastCatalogError.value
        ) {
            error(next.catalog.upload_error, 'Import failed');
        }

        lastCatalogError.value = next.catalog.upload_error;

        wasCatalogInFlight.value ||= next.catalog.in_flight;
        wasOrdersInFlight.value ||= next.orders.in_flight;

        if (wasCatalogInFlight.value && !next.catalog.in_flight) {
            wasCatalogInFlight.value = false;
            reloadCurrentImportPage('catalog');

            const last = next.catalog.last_result;

            if (last?.success) {
                success(catalogSuccessMessage(last));
            } else if (last && !last.success) {
                error(
                    last.message ?? 'Catalog import failed.',
                    'Import failed',
                );
            }
        }

        if (wasOrdersInFlight.value && !next.orders.in_flight) {
            wasOrdersInFlight.value = false;
            reloadCurrentImportPage('orders');

            const last = next.orders.last_result;

            if (last?.success) {
                success(ordersSuccessMessage(last));
            } else if (last && !last.success) {
                error(last.message ?? 'Order import failed.', 'Import failed');
            }
        }

        if (next.catalog.in_flight || next.orders.in_flight) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (imports.value.catalog.in_flight || imports.value.orders.in_flight) {
        startPolling();
    }
});

onUnmounted(stopPolling);
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <MfTopNav />
        <main>
            <MfPageContainer>
                <slot />
            </MfPageContainer>
        </main>
        <GlobalImportModal />
        <MfToast />
        <MfConfirmDialog />
    </div>
</template>
