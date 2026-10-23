<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CatalogPageHeader from '@/components/catalog/CatalogPageHeader.vue';
import CatalogPrintingsTable from '@/components/catalog/CatalogPrintingsTable.vue';
import MfMonospaceId from '@/components/MfMonospaceId.vue';
import { show as productShow } from '@/routes/catalog/products';
import { show as setShow } from '@/routes/catalog/products/sets';

type Printing = {
    finish: string;
    tcgplayer_id: number | null;
    nm_market_price: number | null;
    nm_low_price: number | null;
    lp_market_price: number | null;
    lp_low_price: number | null;
    mp_market_price: number | null;
    mp_low_price: number | null;
};

defineProps<{
    product: {
        id: number;
        label: string;
        priced_at: string | null;
        is_stale: boolean;
    };
    set: { id: number; name: string };
    card: {
        id: number;
        name: string;
        number: string;
        rarity: string;
        image_url: string | null;
    };
    heading: string;
    printings: Printing[];
}>();
</script>

<template>
    <div>
        <Head :title="heading" />

        <CatalogPageHeader
            :title="heading"
            :product="product"
            :breadcrumbs="[
                { label: product.label, route: productShow(product.id).url },
                {
                    label: set.name,
                    route: setShow({ product: product.id, set: set.id }).url,
                },
            ]"
        />

        <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
            <div>
                <img
                    v-if="card.image_url"
                    :src="card.image_url"
                    :alt="card.name"
                    loading="lazy"
                    class="w-full rounded-lg border border-border"
                    data-test="catalog-card-image"
                />
                <div
                    v-else
                    class="flex aspect-[5/7] w-full flex-col items-center justify-center gap-2 rounded-lg border border-border bg-muted text-sm text-muted-foreground"
                    data-test="catalog-card-image-placeholder"
                >
                    <i class="pi pi-image text-2xl" aria-hidden="true" />
                    <span>No image</span>
                </div>
            </div>

            <dl
                class="grid content-start gap-x-6 gap-y-3 text-sm sm:grid-cols-[8rem_1fr]"
                data-test="catalog-card-details"
            >
                <dt class="text-muted-foreground">Name</dt>
                <dd class="font-medium text-foreground">{{ card.name }}</dd>

                <dt class="text-muted-foreground">Number</dt>
                <dd><MfMonospaceId :value="card.number" /></dd>

                <dt class="text-muted-foreground">Set</dt>
                <dd>
                    <Link
                        :href="
                            setShow({ product: product.id, set: set.id }).url
                        "
                        class="hover:text-mf-orange hover:underline"
                        data-test="catalog-card-set-link"
                    >
                        {{ set.name }}
                    </Link>
                </dd>

                <dt class="text-muted-foreground">Rarity</dt>
                <dd class="text-foreground">{{ card.rarity || '—' }}</dd>
            </dl>
        </div>

        <section class="mt-8">
            <h2 class="mb-3 text-base font-semibold text-foreground">
                Printings
            </h2>
            <div
                class="overflow-hidden rounded-lg border border-border"
                data-test="catalog-card-printings"
            >
                <CatalogPrintingsTable :variants="printings" />
            </div>
        </section>
    </div>
</template>
