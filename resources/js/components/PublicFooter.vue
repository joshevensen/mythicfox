<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { copyrightYearLabel } from '@/lib/copyrightYear';
import { login } from '@/routes';

const yearLabel = computed(() => copyrightYearLabel(new Date().getFullYear()));
</script>

<template>
    <footer
        class="border-t border-[#FCFAEF]/10 px-6 py-12 text-center text-xs text-[#FCFAEF]/35"
    >
        <p class="mb-3">
            © {{ yearLabel }} Mythic Fox Games. All rights reserved.
        </p>
        <div class="flex items-center justify-center gap-4">
            <a href="#" class="transition-colors hover:text-[#FCFAEF]/65">
                Terms of Service
            </a>
            <span>·</span>
            <a href="#" class="transition-colors hover:text-[#FCFAEF]/65">
                Privacy Policy
            </a>
            <span>·</span>
            <Link
                :href="login().url"
                class="transition-colors hover:text-[#FCFAEF]/65"
            >
                Admin
            </Link>
        </div>
    </footer>
</template>
