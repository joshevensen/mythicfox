<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MfFormField from '@/components/MfFormField.vue';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Sign in — Mythic Fox Games',
        description: 'Sign in to the Mythic Fox Games admin.',
    },
});

const page = usePage<{ errors: Record<string, string> }>();

type Banner = { tone: 'error'; message: string };

const banner = computed<Banner | null>(() => {
    const errors = page.props.errors ?? {};
    const emailError = errors.email;
    const passwordError = errors.password;

    if (emailError && /\d+\s*seconds?/i.test(emailError)) {
        return { tone: 'error', message: emailError };
    }

    if (emailError || passwordError) {
        return { tone: 'error', message: 'Email or password incorrect.' };
    }

    return null;
});
</script>

<template>
    <div class="flex min-h-[80vh] items-center justify-center px-4 py-12">
        <div
            class="w-full max-w-md rounded-lg border border-border bg-card p-8 shadow-sm"
        >
            <div class="mb-6 flex flex-col items-center gap-3">
                <img
                    src="/mythicfox.png"
                    alt="Mythic Fox Games"
                    class="h-16 w-16"
                />
                <h1 class="text-xl font-semibold text-foreground">Sign in</h1>
            </div>

            <div
                v-if="banner"
                role="alert"
                class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                data-test="login-error"
            >
                {{ banner.message }}
            </div>

            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                :reset-on-error="['password']"
                v-slot="{ processing }"
                class="flex flex-col gap-4"
            >
                <MfFormField label="Email" name="email" required>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        autofocus
                        required
                        :disabled="processing"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </MfFormField>

                <MfFormField label="Password" name="password" required>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        :disabled="processing"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </MfFormField>

                <button
                    type="submit"
                    :disabled="processing"
                    data-test="login-submit"
                    class="mt-2 inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-mf-orange px-4 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <i v-if="processing" class="pi pi-spinner animate-spin" />
                    Sign in
                </button>
            </Form>
        </div>
    </div>
</template>
