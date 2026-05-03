<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type Feedback = {
    text: string;
    rating: number;
    author: string;
    date: string;
};

type SellerStats = {
    rating: number;
    review_count: number;
    feedback: Feedback[];
};

const props = defineProps<{
    tcgplayerStorefrontUrl: string | null;
    sellerStats: SellerStats | null;
    showBuyersSay: boolean;
}>();

defineOptions({
    layout: {
        title: 'Mythic Fox Games — Buy & Sell Trading Card Games',
        description:
            'Mythic Fox Games is a TCGPlayer storefront for Magic: The Gathering, Lorcana, and Flesh & Blood. Pack-fresh cards, honest grading, fast shipping.',
    },
});

const features = [
    {
        icon: 'pi pi-box',
        title: 'Pack-fresh inventory',
        body: 'Most cards come straight from sealed product we open ourselves. Never played, never shuffled.',
    },
    {
        icon: 'pi pi-check-circle',
        title: 'Honest condition',
        body: 'Cards graded conservatively. NM means NM.',
    },
    {
        icon: 'pi pi-shield',
        title: 'Protective packaging',
        body: 'Penny sleeves and TCGuardian shipping protectors. Cards arrive flat and dry.',
    },
    {
        icon: 'pi pi-send',
        title: 'Fast shipping',
        body: 'Orders pack and ship within 1 business day.',
    },
];

const ratingStars = computed(() => {
    if (!props.sellerStats) {
        return [];
    }

    const filled = Math.round(props.sellerStats.rating);

    return Array.from({ length: 5 }, (_, i) =>
        i < filled ? 'pi pi-star-fill' : 'pi pi-star',
    );
});

const organizationJsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'Mythic Fox Games',
        url: 'https://mythicfoxgames.com',
        logo: 'https://mythicfoxgames.com/mythicfox.png',
    }),
);
</script>

<template>
    <Head>
        <!-- Vue's template parser refuses a literal <script> tag, so we emit it via the dynamic-component trick. -->
        <!-- eslint-disable vue/no-v-text-v-html-on-component -->
        <component
            :is="'script'"
            type="application/ld+json"
            v-html="organizationJsonLd"
        />
        <!-- eslint-enable vue/no-v-text-v-html-on-component -->
    </Head>

    <div class="mx-auto w-full max-w-5xl px-4 py-10 sm:py-14">
        <!-- HERO -->
        <section
            class="flex flex-col items-center gap-6 py-8 text-center sm:py-14"
        >
            <img
                src="/mythicfox.png"
                alt="Mythic Fox Games"
                class="h-24 w-24 sm:h-32 sm:w-32"
            />
            <h1 class="text-3xl font-semibold text-mf-brown sm:text-4xl">
                We buy &amp; sell trading card games.
            </h1>
            <a
                v-if="tcgplayerStorefrontUrl"
                :href="tcgplayerStorefrontUrl"
                target="_blank"
                rel="noopener"
                data-test="hero-shop-cta"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-mf-orange px-6 py-3 text-base font-medium text-white shadow-sm transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
            >
                Shop on TCGPlayer →
            </a>
        </section>

        <!-- ABOUT -->
        <section class="mx-auto max-w-3xl py-10 sm:py-14">
            <p class="text-base leading-relaxed text-foreground sm:text-lg">
                Mythic Fox Games is a TCGPlayer storefront. We buy and sell any
                trading card game — currently specializing in Magic: The
                Gathering, Lorcana, and Flesh &amp; Blood. Most of our inventory
                comes from sealed product we open ourselves, so cards arrive
                pack-fresh — never played, never shuffled. Every card is graded
                honestly, packed in penny sleeves with TCGuardian shipping
                protectors, and shipped within one business day. If anything
                arrives wrong, message me — I'll make it right.
            </p>
        </section>

        <!-- WHAT YOU GET -->
        <section class="py-10 sm:py-14">
            <h2 class="mb-8 text-center text-2xl font-semibold text-mf-brown">
                What you get
            </h2>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="flex flex-col items-start gap-3 rounded-lg border border-border bg-card p-5"
                >
                    <i :class="[feature.icon, 'text-2xl text-mf-orange']" />
                    <h3 class="text-lg font-semibold text-foreground">
                        {{ feature.title }}
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        {{ feature.body }}
                    </p>
                </div>
            </div>
        </section>

        <!-- WHAT BUYERS SAY -->
        <section
            v-if="showBuyersSay && sellerStats"
            data-test="buyers-say"
            class="py-10 sm:py-14"
        >
            <h2 class="mb-6 text-center text-2xl font-semibold text-mf-brown">
                What buyers say
            </h2>
            <div
                class="mb-8 flex items-center justify-center gap-2 text-lg"
                data-test="buyers-say-rating"
            >
                <span
                    v-for="(star, idx) in ratingStars"
                    :key="idx"
                    :class="[star, 'text-mf-orange']"
                />
                <span class="ml-2 text-foreground">
                    {{ sellerStats.rating.toFixed(1) }} from
                    {{ sellerStats.review_count }} reviews on TCGPlayer
                </span>
            </div>
            <div
                v-if="sellerStats.feedback.length > 0"
                class="grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <article
                    v-for="(item, idx) in sellerStats.feedback"
                    :key="idx"
                    class="flex flex-col gap-3 rounded-lg border border-border bg-card p-5"
                >
                    <p class="text-sm leading-relaxed text-mf-brown">
                        “{{ item.text }}”
                    </p>
                    <div class="text-xs text-muted-foreground">
                        — {{ item.author }} · {{ item.date }}
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
