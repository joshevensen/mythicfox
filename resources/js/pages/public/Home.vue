<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Lock,
    MessageSquare,
    Shield,
    Star,
    Tag,
    ThumbsUp,
    Truck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { sellToUs } from '@/routes';

defineProps<{
    tcgplayerStorefrontUrl: string | null;
}>();

const mainFeatures = [
    {
        icon: Tag,
        title: 'Great Prices',
        body: 'We offer some of the most competitive prices on the market for both buyers and sellers.',
    },
    {
        icon: Shield,
        title: 'Trusted & Secure',
        body: 'Safe transactions, secure payments, and data protection you can count on.',
    },
    {
        icon: Truck,
        title: 'Fast & Reliable',
        body: 'Quick shipping and fast payments so you can get back to what you love.',
    },
];

const aboutFeatures = [
    { icon: Star, title: 'Collector Focused' },
    { icon: ThumbsUp, title: 'Fair & Honest Deals' },
    { icon: Lock, title: 'Safe & Secure Payments' },
    { icon: MessageSquare, title: 'Responsive Support' },
];

const organizationJsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'Mythic Fox Games',
        url: 'https://mythicfoxgames.com',
        logo: 'https://mythicfoxgames.com/logo.png',
    }),
);
</script>

<template>
    <Head>
        <title>Mythic Fox Games — Buy & Sell Trading Card Games</title>
        <meta
            name="description"
            content="Mythic Fox Games is your trusted source for buying and selling TCG singles. We offer competitive prices, fast payments, and a seamless experience for collectors and players alike."
        />
        <!-- eslint-disable vue/no-v-text-v-html-on-component -->
        <component
            :is="'script'"
            type="application/ld+json"
            v-html="organizationJsonLd"
        />
        <!-- eslint-enable vue/no-v-text-v-html-on-component -->
    </Head>

    <div>
        <!-- HERO -->
        <section class="relative flex min-h-[85vh] flex-col">
            <!-- Background forest image with overlay -->
            <div class="absolute inset-0 overflow-hidden">
                <img
                    src="/forest-image.png"
                    alt=""
                    class="h-full w-full object-cover object-top"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-r from-[#12100C]/80 via-[#12100C]/65 to-[#12100C]/5"
                />
                <div
                    class="absolute right-0 bottom-0 left-0 h-40 bg-gradient-to-t from-[#12100C] to-transparent"
                />
            </div>

            <!-- Logo -->
            <div class="relative z-10 flex justify-center pt-8 pb-4">
                <img
                    src="/logo.png"
                    alt="Mythic Fox Games"
                    class="h-14 w-auto"
                />
            </div>

            <!-- Hero content -->
            <div
                class="relative z-10 mx-auto flex w-full max-w-6xl flex-1 items-center px-6 pb-20 sm:px-10"
            >
                <div class="max-w-xl">
                    <h1
                        class="mb-6 text-5xl leading-tight font-bold sm:text-6xl"
                    >
                        We buy and<br />
                        Sell
                        <span class="text-[#F38B17]">TCG Cards</span>
                    </h1>
                    <p
                        class="mb-8 text-base leading-relaxed text-[#FCFAEF]/75 sm:text-lg"
                    >
                        Whether you're looking to buy singles or sell your
                        collection, Mythic Fox Games offers competitive prices,
                        fast payments, and honest deals for collectors and
                        players alike.
                    </p>
                    <div class="flex flex-wrap items-center gap-4">
                        <a
                            v-if="tcgplayerStorefrontUrl"
                            :href="tcgplayerStorefrontUrl"
                            target="_blank"
                            rel="noopener"
                            data-test="hero-shop-cta"
                            class="inline-flex items-center gap-2 rounded border border-[#F38B17] px-6 py-3 text-sm font-semibold tracking-widest text-[#F38B17] uppercase transition-colors hover:bg-[#F38B17] hover:text-[#12100C]"
                        >
                            Browse Singles →
                        </a>
                        <Link
                            :href="sellToUs().url"
                            data-test="hero-sell-cta"
                            class="inline-flex items-center gap-2 rounded border border-[#F38B17] px-6 py-3 text-sm font-semibold tracking-widest text-[#F38B17] uppercase transition-colors hover:bg-[#F38B17] hover:text-[#12100C]"
                        >
                            Sell Your Collection →
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- MAIN FEATURES -->
        <section class="px-6 py-16">
            <div class="mx-auto max-w-5xl">
                <div class="grid grid-cols-1 gap-10 sm:grid-cols-3">
                    <div
                        v-for="feature in mainFeatures"
                        :key="feature.title"
                        class="flex flex-col items-center gap-4 text-center"
                    >
                        <component
                            :is="feature.icon"
                            :size="64"
                            class="text-[#F38B17]"
                        />
                        <h3 class="text-lg font-semibold text-[#FCFAEF]">
                            {{ feature.title }}
                        </h3>
                        <p class="text-sm leading-relaxed text-[#FCFAEF]/55">
                            {{ feature.body }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ABOUT US -->
        <section class="px-6 py-16">
            <div class="mx-auto max-w-3xl text-center">
                <div class="mb-4 flex items-center justify-center gap-3">
                    <div class="h-px w-10 bg-[#F38B17]/40" />
                    <span
                        class="text-xs font-semibold tracking-widest text-[#F38B17] uppercase"
                    >
                        About Us
                    </span>
                    <div class="h-px w-10 bg-[#F38B17]/40" />
                </div>
                <h2 class="mb-6 text-3xl font-bold text-[#FCFAEF] sm:text-4xl">
                    Built for Collectors,<br />by Collectors
                </h2>
                <p class="mb-14 text-base leading-relaxed text-[#FCFAEF]/65">
                    Mythic Fox Games was built by passionate collectors, for
                    collectors. We believe in fair deals, honest communication,
                    and helping the TCG community grow. Whether you're looking
                    to sell your collection or find that missing piece, we're
                    here to help.
                </p>
            </div>

            <div class="mx-auto max-w-4xl">
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                    <div
                        v-for="feat in aboutFeatures"
                        :key="feat.title"
                        class="flex flex-col items-center gap-3 text-center"
                    >
                        <component
                            :is="feat.icon"
                            :size="64"
                            class="text-[#F38B17]"
                        />
                        <p class="text-sm font-medium text-[#FCFAEF]/75">
                            {{ feat.title }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
