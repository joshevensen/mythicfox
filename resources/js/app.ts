import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import 'primeicons/primeicons.css';
import { initializeTheme } from '@/composables/useAppearance';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { MythicFoxPreset } from '@/lib/primevue-preset';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AdminLayout, SettingsLayout];
            case name === 'Dashboard':
            case name === 'Settings':
            case name === 'AddCards':
            case name.startsWith('Cards/'):
            case name.startsWith('Decks/'):
            case name.startsWith('Inventory/'):
            case name.startsWith('Orders/'):
            case name.startsWith('placeholders/'):
                return AdminLayout;
            default:
                return AppLayout;
        }
    },
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

// This will listen for flash toast data from the server...
initializeFlashToast();
