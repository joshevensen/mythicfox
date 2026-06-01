<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import MfMoney from '@/components/MfMoney.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import EditProductRulesModal from '@/components/settings/EditProductRulesModal.vue';
import EditSetRulesModal from '@/components/settings/EditSetRulesModal.vue';
import FileHistorySection from '@/components/settings/FileHistorySection.vue';
import SellerStatsSection from '@/components/settings/SellerStatsSection.vue';
import { index as catalogIndex } from '@/routes/catalog';

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

type SellerStatsPayload = {
    rating: number | null;
    review_count: number | null;
    feedback: Array<{
        text?: string;
        rating?: number;
        author?: string;
        date?: string;
    }>;
    feedback_count: number;
    scraped_at: string | null;
    last_attempt_at: string | null;
    last_error: string | null;
    consecutive_failures: number;
    status: {
        key: 'healthy' | 'stale' | 'failed' | 'hidden' | 'unknown';
        label: string;
        message: string | null;
    };
    raw: Record<string, unknown>;
    refreshing: boolean;
};

type SetRow = {
    id: number;
    name: string;
    base_price: number | null;
    high_price: number | null;
    market_offset: number | null;
    high_offset: number | null;
    overridden: boolean;
};

type ProductRow = {
    id: number;
    name: string;
    base_price: number;
    high_price: number;
    market_offset: number;
    high_offset: number;
    sets: SetRow[];
    sets_count: number;
};

defineProps<{
    products: ProductRow[];
    files: FilesPayload;
    filePurposes: PurposeOption[];
    sellerStats: SellerStatsPayload;
}>();

const editingProduct = ref<ProductRow | null>(null);
const productModalVisible = ref(false);

const editingSet = ref<SetRow | null>(null);
const editingSetProduct = ref<ProductRow | null>(null);
const setModalVisible = ref(false);

const openProductModal = (product: ProductRow) => {
    editingProduct.value = product;
    productModalVisible.value = true;
};

const openSetModal = (product: ProductRow, set: SetRow) => {
    editingSet.value = set;
    editingSetProduct.value = product;
    setModalVisible.value = true;
};

const refresh = () => {
    router.reload({ only: ['products'] });
};
</script>

<template>
    <Head title="Settings" />

    <MfPageHeader title="Settings" />
    <p class="-mt-4 mb-6 text-sm text-muted-foreground">
        Manage pricing rules and review import/export history.
    </p>

    <nav
        aria-label="Settings sections"
        class="mb-8 flex flex-wrap gap-2"
        data-test="settings-toc"
    >
        <a
            href="#pricing-rules"
            class="rounded-full border border-border px-4 py-1.5 text-sm font-medium text-foreground transition-colors hover:border-mf-orange hover:text-mf-orange"
        >
            Pricing rules
        </a>
        <a
            href="#file-history"
            class="rounded-full border border-border px-4 py-1.5 text-sm font-medium text-foreground transition-colors hover:border-mf-orange hover:text-mf-orange"
        >
            File history
        </a>
        <a
            href="#seller-stats"
            class="rounded-full border border-border px-4 py-1.5 text-sm font-medium text-foreground transition-colors hover:border-mf-orange hover:text-mf-orange"
        >
            Seller stats
        </a>
    </nav>

    <section
        id="pricing-rules"
        class="scroll-mt-20"
        data-test="pricing-rules-section"
    >
        <h2 class="mb-4 text-xl font-semibold text-foreground">
            Pricing rules
        </h2>

        <div
            v-if="products.length === 0"
            class="rounded-lg border border-dashed border-border p-6 text-center text-muted-foreground"
            data-test="pricing-rules-empty"
        >
            <p>
                No products yet — they'll appear after your first
                PricingCustomExport upload.
            </p>
            <Link
                :href="catalogIndex().url"
                class="mt-2 inline-block text-mf-orange hover:underline"
            >
                Go to Cards
            </Link>
        </div>

        <div v-else class="flex flex-col gap-8">
            <article
                v-for="product in products"
                :key="product.id"
                class="rounded-lg border border-border bg-card p-4"
                :data-test="`product-${product.id}`"
            >
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-3 text-left"
                    :data-test="`edit-product-${product.id}`"
                    @click="openProductModal(product)"
                >
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-foreground">
                            {{ product.name }}
                        </h3>
                        <p
                            class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted-foreground"
                            :data-test="`product-${product.id}-summary`"
                        >
                            <span>
                                base
                                <MfMoney
                                    :cents="product.base_price"
                                    align="left"
                                />
                            </span>
                            <span aria-hidden="true">•</span>
                            <span>
                                high
                                <MfMoney
                                    :cents="product.high_price"
                                    align="left"
                                />
                            </span>
                            <span aria-hidden="true">•</span>
                            <span>
                                market −<MfMoney
                                    :cents="product.market_offset"
                                    align="left"
                                />
                            </span>
                            <span aria-hidden="true">•</span>
                            <span>
                                high −<MfMoney
                                    :cents="product.high_offset"
                                    align="left"
                                />
                            </span>
                        </p>
                    </div>
                    <i
                        class="pi pi-pencil text-muted-foreground"
                        aria-hidden="true"
                    />
                </button>

                <div class="mt-4">
                    <h4 class="mb-2 text-sm font-medium text-muted-foreground">
                        Sets ({{ product.sets_count }})
                    </h4>
                    <ul
                        v-if="product.sets.length > 0"
                        class="divide-y divide-border rounded-md border border-border"
                    >
                        <li v-for="set in product.sets" :key="set.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                                :data-test="`edit-set-${set.id}`"
                                @click="openSetModal(product, set)"
                            >
                                <span>{{ set.name }}</span>
                                <span
                                    v-if="set.overridden"
                                    class="rounded-full bg-mf-teal/15 px-2 py-0.5 text-xs font-medium text-mf-teal"
                                    data-test="overridden-badge"
                                >
                                    overridden
                                </span>
                            </button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No sets yet for this product.
                    </p>
                </div>
            </article>
        </div>
    </section>

    <FileHistorySection :files="files" :purposes="filePurposes" />

    <SellerStatsSection :seller-stats="sellerStats" />

    <EditProductRulesModal
        v-model:visible="productModalVisible"
        :product="editingProduct"
        @saved="refresh"
    />

    <EditSetRulesModal
        v-model:visible="setModalVisible"
        :set="editingSet"
        :product="editingSetProduct"
        @saved="refresh"
    />
</template>
