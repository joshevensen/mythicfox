<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type Props = {
    title: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <meta v-if="description" name="description" :content="description" />
    </Head>
    <div
        class="flex min-h-screen flex-col bg-background text-foreground"
    >
        <main class="flex-1">
            <slot />
        </main>
        <!-- Footer placeholder — replaced by PublicFooter in task 40-004 -->
        <footer />
    </div>
</template>
