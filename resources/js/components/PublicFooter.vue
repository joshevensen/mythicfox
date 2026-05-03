<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { copyrightYearLabel } from '@/lib/copyrightYear';
import { login } from '@/routes';

const yearLabel = computed(() => copyrightYearLabel(new Date().getFullYear()));
</script>

<template>
    <footer
        class="mt-12 border-t border-border px-4 py-6 text-center text-sm text-muted-foreground"
    >
        <span>© {{ yearLabel }} Mythic Fox Games</span>
        <span aria-hidden="true" class="mx-2">·</span>
        <Link
            :href="login().url"
            class="text-muted-foreground underline-offset-2 hover:underline"
        >
            Admin
        </Link>
    </footer>
</template>
