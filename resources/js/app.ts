import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import 'primeicons/primeicons.css';
import { initializeTheme } from '@/composables/useAppearance';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { MythicFoxPreset } from '@/lib/primevue-preset';
import { resolveLayout } from '@/lib/resolveLayout';
import type { LayoutKey } from '@/lib/resolveLayout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const layouts = {
    none: null,
    public: PublicLayout,
    auth: AuthLayout,
    settings: [AdminLayout, SettingsLayout],
    admin: AdminLayout,
} satisfies Record<LayoutKey, unknown>;

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => layouts[resolveLayout(name)],
    progress: {
        color: '#4B5563',
    },
    withApp: (app) => {
        app.use(PrimeVue, {
            theme: {
                preset: MythicFoxPreset,
                options: {
                    darkModeSelector: '.dark',
                },
            },
        });
        app.use(ConfirmationService);
        app.use(ToastService);
    },
});

// This will set light / dark mode on page load...
initializeTheme();
