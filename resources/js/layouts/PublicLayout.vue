<script setup lang="ts">
import PublicFooter from '@/components/PublicFooter.vue';
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-[#12100C] font-sans text-[#FCFAEF]"
    >
        <main class="flex flex-1 flex-col">
            <slot />
        </main>
        <PublicFooter />
    </div>
</template>
