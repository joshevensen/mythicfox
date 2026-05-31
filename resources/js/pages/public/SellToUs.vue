<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home } from '@/routes';
</script>

<template>
    <Head>
        <title>Sell to Us — Mythic Fox Games</title>
        <meta
            name="description"
            content="Sell your TCG singles and collections to Mythic Fox Games. Coming soon."
        />
    </Head>

    <div
        class="flex flex-1 flex-col items-center justify-center px-6 py-20 text-center"
    >
        <Link :href="home().url" class="mb-10 block">
            <img src="/logo.png" alt="Mythic Fox Games" class="h-14 w-auto" />
        </Link>

        <div class="mb-4 flex items-center justify-center gap-3">
            <div class="h-px w-10 bg-[#F38B17]/40" />
            <span
                class="text-xs font-semibold tracking-widest text-[#F38B17] uppercase"
            >
                Coming Soon
            </span>
            <div class="h-px w-10 bg-[#F38B17]/40" />
        </div>

        <h1 class="mb-6 text-4xl font-bold sm:text-5xl">
            Sell Your Collection
        </h1>

        <p class="mb-10 max-w-md text-base leading-relaxed text-[#FCFAEF]/65">
            We're building a dedicated selling experience. In the meantime,
            reach out directly and we'll take care of you.
        </p>

        <div class="flex flex-wrap items-center justify-center gap-4">
            <a
                href="mailto:josh@mythicfoxgames.com"
                class="inline-flex items-center gap-2 rounded border border-[#F38B17] px-6 py-3 text-sm font-semibold tracking-widest text-[#F38B17] uppercase transition-colors hover:bg-[#F38B17] hover:text-[#12100C]"
            >
                Get in Touch →
            </a>
            <Link
                :href="home().url"
                class="inline-flex items-center gap-2 rounded border border-[#FCFAEF]/25 px-6 py-3 text-sm font-semibold tracking-widest text-[#FCFAEF]/60 uppercase transition-colors hover:border-[#FCFAEF]/50 hover:text-[#FCFAEF]"
            >
                ← Back to Home
            </Link>
        </div>
    </div>
</template>
