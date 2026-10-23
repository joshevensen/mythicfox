<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import Drawer from 'primevue/drawer';
import Popover from 'primevue/popover';
import { computed, ref, useTemplateRef } from 'vue';
import { useGlobalImportModal } from '@/composables/useGlobalImportModal';
import { dashboard, logout } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import { show as productShow } from '@/routes/catalog/products';
import { index as ordersIndex } from '@/routes/orders';
import { edit as editProfile } from '@/routes/profile';
import type { User } from '@/types';

type Section = {
    label: string;
    href: string;
    matchPrefix: string;
    /** Render as a product menu instead of a plain link. */
    dropdown?: boolean;
};

const SECTIONS: Section[] = [
    { label: 'Dashboard', href: dashboard().url, matchPrefix: '/dashboard' },
    { label: 'Orders', href: ordersIndex().url, matchPrefix: '/orders' },
    {
        label: 'Catalog',
        href: catalogIndex().url,
        matchPrefix: '/catalog',
        dropdown: true,
    },
    { label: 'Settings', href: editProfile().url, matchPrefix: '/settings' },
];

const page = usePage<{
    auth: { user: User };
    catalog_products?: { id: number; label: string }[];
}>();

const currentUrl = computed(() => page.url);

const isActive = (section: Section): boolean =>
    currentUrl.value === section.matchPrefix ||
    currentUrl.value.startsWith(`${section.matchPrefix}/`);

const catalogProducts = computed(() => page.props.catalog_products ?? []);

const isCatalogProductActive = (id: number): boolean =>
    currentUrl.value === `/catalog/${id}` ||
    currentUrl.value.startsWith(`/catalog/${id}/`);

const userName = computed(() => page.props.auth?.user?.name ?? '');

const userPopover = useTemplateRef<InstanceType<typeof Popover>>('userPopover');

const toggleUserMenu = (event: MouseEvent) => {
    userPopover.value?.toggle(event);
};

const catalogPopover =
    useTemplateRef<InstanceType<typeof Popover>>('catalogPopover');

const toggleCatalogMenu = (event: MouseEvent) => {
    catalogPopover.value?.toggle(event);
};

const hideCatalogMenu = (): void => {
    catalogPopover.value?.hide();
};

const drawerOpen = ref(false);
const importModal = useGlobalImportModal();

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
            class="flex items-center"
            aria-label="Mythic Fox Games"
        >
            <img src="/logo.png" alt="Mythic Fox Games" class="h-8 w-auto" />
        </Link>

        <nav class="ml-6 hidden items-center gap-1 md:flex">
            <template v-for="section in SECTIONS" :key="section.label">
                <button
                    v-if="section.dropdown"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                        isActive(section)
                            ? 'bg-mf-orange/10 text-mf-orange'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    :data-test="`topnav-link-${section.label.toLowerCase()}`"
                    :aria-current="isActive(section) ? 'page' : undefined"
                    aria-haspopup="true"
                    @click="toggleCatalogMenu"
                >
                    {{ section.label }}
                    <i class="pi pi-chevron-down text-xs" aria-hidden="true" />
                </button>
                <Link
                    v-else
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
            </template>

            <Popover ref="catalogPopover">
                <div class="flex w-56 flex-col gap-1 p-1">
                    <Link
                        v-for="p in catalogProducts"
                        :key="p.id"
                        :href="productShow(p.id).url"
                        :class="[
                            'rounded-md px-3 py-2 text-sm',
                            isCatalogProductActive(p.id)
                                ? 'bg-mf-orange/10 text-mf-orange'
                                : 'text-foreground hover:bg-muted',
                        ]"
                        :data-test="`topnav-catalog-product-${p.id}`"
                        @click="hideCatalogMenu"
                    >
                        {{ p.label }}
                    </Link>
                    <Link
                        v-if="catalogProducts.length === 0"
                        :href="catalogIndex().url"
                        class="rounded-md px-3 py-2 text-sm text-foreground hover:bg-muted"
                        data-test="topnav-catalog-index"
                        @click="hideCatalogMenu"
                    >
                        Catalog
                    </Link>
                </div>
            </Popover>
        </nav>

        <div class="ml-auto flex items-center">
            <button
                type="button"
                class="mr-1 inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground hover:bg-muted"
                aria-label="Open import modal"
                title="Import files"
                data-test="topnav-import-button"
                @click="importModal.open()"
            >
                <i class="pi pi-upload" />
            </button>

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
                <img
                    src="/logo.png"
                    alt="Mythic Fox Games"
                    class="h-8 w-auto"
                />
            </template>
            <nav class="flex flex-col gap-1">
                <template v-for="section in SECTIONS" :key="section.label">
                    <template
                        v-if="section.dropdown && catalogProducts.length > 0"
                    >
                        <span
                            :class="[
                                'rounded-md px-3 py-2 text-sm font-medium',
                                isActive(section)
                                    ? 'text-mf-orange'
                                    : 'text-foreground',
                            ]"
                            :data-test="`drawer-link-${section.label.toLowerCase()}`"
                        >
                            {{ section.label }}
                        </span>
                        <Link
                            v-for="p in catalogProducts"
                            :key="p.id"
                            :href="productShow(p.id).url"
                            :class="[
                                'rounded-md py-2 pr-3 pl-6 text-sm',
                                isCatalogProductActive(p.id)
                                    ? 'bg-mf-orange/10 text-mf-orange'
                                    : 'text-foreground hover:bg-muted',
                            ]"
                            :data-test="`drawer-catalog-product-${p.id}`"
                            @click="drawerOpen = false"
                        >
                            {{ p.label }}
                        </Link>
                    </template>
                    <Link
                        v-else
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
                </template>
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
