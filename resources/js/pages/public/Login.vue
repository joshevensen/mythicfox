<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MfFormField from '@/components/MfFormField.vue';
import { store } from '@/routes/login';

const page = usePage<{ errors: Record<string, string> }>();

type Banner = { tone: 'error'; message: string };

const countdown = ref(0);
const serverError = ref(false);
let countdownTimer: ReturnType<typeof setInterval> | null = null;
let unbindError: (() => void) | null = null;

function clearCountdown() {
    if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
}

function startCountdown(seconds: number) {
    clearCountdown();
    countdown.value = seconds;
    countdownTimer = setInterval(() => {
        countdown.value = Math.max(0, countdown.value - 1);

        if (countdown.value === 0) {
            clearCountdown();
        }
    }, 1000);
}

watch(
    () => page.props.errors?.email,
    (message) => {
        if (!message) {
            return;
        }

        const match = /(\d+)\s*seconds?/i.exec(message);

        if (match) {
            startCountdown(parseInt(match[1], 10));
        }
    },
    { immediate: true },
);

onMounted(() => {
    // router.on('error') fires for non-validation Inertia errors (5xx, network).
    unbindError = router.on('error', () => {
        serverError.value = true;
    });
});

onBeforeUnmount(() => {
    clearCountdown();
    unbindError?.();
});

function handleBeforeSubmit() {
    serverError.value = false;
}

const submitBlocked = computed(() => countdown.value > 0);

const banner = computed<Banner | null>(() => {
    if (countdown.value > 0) {
        return {
            tone: 'error',
            message: `Too many attempts — try again in ${countdown.value} seconds.`,
        };
    }

    const errors = page.props.errors ?? {};
    const emailError = errors.email;
    const passwordError = errors.password;

    if (emailError || passwordError) {
        return { tone: 'error', message: 'Email or password incorrect.' };
    }

    if (serverError.value) {
        return { tone: 'error', message: 'Something went wrong. Try again.' };
    }

    return null;
});
</script>

<template>
    <Head>
        <title>Sign in — Mythic Fox Games</title>
        <meta
            name="description"
            content="Sign in to the Mythic Fox Games admin."
        />
    </Head>

    <div class="flex min-h-[80vh] items-center justify-center px-4 py-12">
        <div
            class="w-full max-w-md rounded-lg border border-border bg-card p-8 shadow-sm"
        >
            <div class="mb-6 flex flex-col items-center gap-3">
                <img
                    src="/logo.png"
                    alt="Mythic Fox Games"
                    class="h-12 w-auto"
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
                @before="handleBeforeSubmit"
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
                        :disabled="processing || submitBlocked"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </MfFormField>

                <MfFormField label="Password" name="password" required>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        :disabled="processing || submitBlocked"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </MfFormField>

                <button
                    type="submit"
                    :disabled="processing || submitBlocked"
                    data-test="login-submit"
                    class="mt-2 inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-mf-orange px-4 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <i v-if="processing" class="pi pi-spinner animate-spin" />
                    Sign in
                </button>
            </Form>
        </div>
    </div>
</template>
