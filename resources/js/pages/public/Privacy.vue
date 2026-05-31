<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home } from '@/routes';
</script>

<template>
    <Head>
        <title>Privacy Policy — Mythic Fox Games</title>
        <meta
            name="description"
            content="Privacy Policy for Mythic Fox Games. Full policy details are coming soon."
        />
    </Head>

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 py-20">
        <Link :href="home().url" class="mb-12 block">
            <img src="/logo.png" alt="Mythic Fox Games" class="h-14 w-auto" />
        </Link>

        <div class="mb-4 flex items-center gap-3">
            <div class="h-px w-10 bg-[#F38B17]/40" />
            <span
                class="text-xs font-semibold tracking-widest text-[#F38B17] uppercase"
            >
                Legal
            </span>
        </div>

        <h1 class="mb-6 text-4xl font-bold sm:text-5xl">Privacy Policy</h1>

        <p class="mb-10 max-w-2xl text-base leading-relaxed text-[#FCFAEF]/65">
            Our full privacy policy is coming soon. Contact Mythic Fox Games if
            you have questions about how we handle customer information.
        </p>

        <Link
            :href="home().url"
            class="inline-flex w-fit items-center gap-2 rounded border border-[#FCFAEF]/25 px-6 py-3 text-sm font-semibold tracking-widest text-[#FCFAEF]/60 uppercase transition-colors hover:border-[#FCFAEF]/50 hover:text-[#FCFAEF]"
        >
            ← Back to Home
        </Link>
    </div>
</template>
