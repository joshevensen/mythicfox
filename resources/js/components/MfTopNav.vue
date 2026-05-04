<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import Drawer from 'primevue/drawer';
import Popover from 'primevue/popover';
import { computed, ref, useTemplateRef } from 'vue';
import { dashboard, logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { User } from '@/types';

type Section = {
    label: string;
    href: string;
    matchPrefix: string;
};

// TODO(phase-50/60): swap placeholder URLs for Wayfinder helpers once
// orders/catalog/inventory routes exist.
const SECTIONS: Section[] = [
    { label: 'Dashboard', href: dashboard().url, matchPrefix: '/dashboard' },
    { label: 'Orders', href: '/orders', matchPrefix: '/orders' },
    { label: 'Inventory', href: '/inventory', matchPrefix: '/inventory' },
    { label: 'Cards', href: '/cards', matchPrefix: '/cards' },
    { label: 'Decks', href: '/decks', matchPrefix: '/decks' },
    { label: 'Settings', href: editProfile().url, matchPrefix: '/settings' },
];

const page = usePage<{ auth: { user: User } }>();

const currentUrl = computed(() => page.url);

const isActive = (section: Section): boolean =>
    currentUrl.value === section.matchPrefix ||
    currentUrl.value.startsWith(`${section.matchPrefix}/`);

const userName = computed(() => page.props.auth?.user?.name ?? '');

const userPopover = useTemplateRef<InstanceType<typeof Popover>>('userPopover');

const toggleUserMenu = (event: MouseEvent) => {
    userPopover.value?.toggle(event);
};

const drawerOpen = ref(false);

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <header
        data-mf-component="topnav"
        class="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-border bg-background/85 px-4 backdrop-blur sm:px-6 lg:px-8"
    >
        <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground hover:bg-muted md:hidden"
            aria-label="Open navigation"
            data-test="topnav-hamburger"
            @click="drawerOpen = true"
        >
            <i class="pi pi-bars text-lg" />
        </button>

        <Link
            :href="dashboard()"
            class="flex items-center gap-2 font-semibold tracking-tight text-mf-orange"
        >
            <i class="pi pi-shield text-xl" />
            <span>Mythic Fox</span>
        </Link>

        <nav class="ml-6 hidden items-center gap-1 md:flex">
            <Link
                v-for="section in SECTIONS"
                :key="section.label"
                :href="section.href"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    isActive(section)
                        ? 'bg-mf-orange/10 text-mf-orange'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                ]"
                :data-test="`topnav-link-${section.label.toLowerCase()}`"
                :aria-current="isActive(section) ? 'page' : undefined"
            >
                {{ section.label }}
            </Link>
        </nav>

        <div class="ml-auto flex items-center">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-foreground hover:bg-muted"
                aria-label="Open user menu"
                data-test="topnav-user-menu"
                @click="toggleUserMenu"
            >
                <span
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-mf-teal/15 text-mf-teal"
                >
                    <i class="pi pi-user" />
                </span>
                <span class="hidden sm:inline">{{ userName }}</span>
            </button>

            <Popover ref="userPopover">
                <div class="flex w-48 flex-col gap-1 p-1">
                    <Link
                        :href="logout()"
                        as="button"
                        method="post"
                        class="flex items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                        data-test="logout-button"
                        @click="handleLogout"
                    >
                        <i class="pi pi-sign-out" />
                        Log out
                    </Link>
                </div>
            </Popover>
        </div>

        <Drawer
            v-model:visible="drawerOpen"
            position="left"
            :show-close-icon="true"
            class="w-72!"
        >
            <template #header>
                <div
                    class="flex items-center gap-2 font-semibold text-mf-orange"
                >
                    <i class="pi pi-shield text-xl" />
                    <span>Mythic Fox</span>
                </div>
            </template>
            <nav class="flex flex-col gap-1">
                <Link
                    v-for="section in SECTIONS"
                    :key="section.label"
                    :href="section.href"
                    :class="[
                        'rounded-md px-3 py-2 text-sm font-medium',
                        isActive(section)
                            ? 'bg-mf-orange/10 text-mf-orange'
                            : 'text-foreground hover:bg-muted',
                    ]"
                    :data-test="`drawer-link-${section.label.toLowerCase()}`"
                    @click="drawerOpen = false"
                >
                    {{ section.label }}
                </Link>
                <div class="my-2 border-t border-border" />
                <Link
                    :href="logout()"
                    as="button"
                    method="post"
                    class="flex items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                    @click="
                        drawerOpen = false;
                        handleLogout();
                    "
                >
                    <i class="pi pi-sign-out" />
                    Log out
                </Link>
            </nav>
        </Drawer>
    </header>
</template>
