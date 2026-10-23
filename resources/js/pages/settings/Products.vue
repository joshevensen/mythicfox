<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import MfMoney from '@/components/MfMoney.vue';
import EditProductRulesModal from '@/components/settings/EditProductRulesModal.vue';
import EditSetRulesModal from '@/components/settings/EditSetRulesModal.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { index as catalogIndex } from '@/routes/catalog';
import { index as productsIndex } from '@/routes/settings/products';

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
    display_name: string | null;
    display_label: string;
    base_price: number;
    high_price: number;
    market_offset: number;
    high_offset: number;
    sets: SetRow[];
    sets_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Product settings',
                href: productsIndex(),
            },
        ],
    },
});

defineProps<{
    products: ProductRow[];
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
    <Head title="Product settings" />

    <h1 class="sr-only">Product settings</h1>

    <section
        id="pricing-rules"
        class="space-y-6"
        data-test="pricing-rules-section"
    >
        <Heading
            variant="small"
            title="Pricing rules"
            description="Set default pricing rules and display names per product."
        />

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
                Go to Catalog
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
                            {{ product.display_label }}
                        </h3>
                        <p
                            v-if="product.display_name !== null"
                            class="text-xs text-muted-foreground"
                            :data-test="`product-${product.id}-tcgplayer-name`"
                        >
                            TCGPlayer: {{ product.name }}
                        </p>
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

                <Collapsible v-slot="{ open }" class="mt-4">
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between gap-2 text-left text-sm font-medium text-muted-foreground hover:text-foreground"
                        :data-test="`toggle-sets-${product.id}`"
                    >
                        <span>Sets ({{ product.sets_count }})</span>
                        <i
                            class="pi pi-chevron-down text-xs transition-transform"
                            :class="{ 'rotate-180': open }"
                            aria-hidden="true"
                        />
                    </CollapsibleTrigger>
                    <CollapsibleContent class="mt-2">
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
                    </CollapsibleContent>
                </Collapsible>
            </article>
        </div>
    </section>

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
