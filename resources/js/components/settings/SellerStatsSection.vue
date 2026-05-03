<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import { computed, ref } from 'vue';
import { refresh as refreshAction } from '@/actions/App/Http/Controllers/Settings/SellerStatsController';
import MfDate from '@/components/MfDate.vue';
import { useMfToast } from '@/composables/useMfToast';

type FeedbackEntry = {
    text?: string;
    rating?: number;
    author?: string;
    date?: string;
};

type Status = {
    key: 'healthy' | 'stale' | 'failed' | 'hidden' | 'unknown';
    label: string;
    message: string | null;
};

type SellerStatsPayload = {
    rating: number | null;
    review_count: number | null;
    feedback: FeedbackEntry[];
    feedback_count: number;
    scraped_at: string | null;
    last_attempt_at: string | null;
    last_error: string | null;
    consecutive_failures: number;
    status: Status;
    raw: Record<string, unknown>;
    refreshing: boolean;
};

const props = defineProps<{
    sellerStats: SellerStatsPayload;
}>();

const { info, success, error } = useMfToast();
const rawDialogVisible = ref(false);

const borderClass = computed(() => {
    switch (props.sellerStats.status.key) {
        case 'failed':
        case 'stale':
            return 'border-amber-500';
        case 'hidden':
            return 'border-red-500';
        default:
            return 'border-border';
    }
});

const iconClass = computed(() => {
    switch (props.sellerStats.status.key) {
        case 'healthy':
            return 'pi pi-check-circle text-emerald-500';
        case 'failed':
        case 'stale':
            return 'pi pi-exclamation-triangle text-amber-500';
        case 'hidden':
            return 'pi pi-times-circle text-red-500';
        default:
            return 'pi pi-info-circle text-muted-foreground';
    }
});

const relativeFromNow = (iso: string | null): string => {
    if (!iso) {
        return 'never';
    }

    const ms = Date.now() - new Date(iso).getTime();
    const days = Math.floor(ms / (1000 * 60 * 60 * 24));

    if (days === 0) {
        return 'today';
    }

    if (days === 1) {
        return '1 day ago';
    }

    return `${days} days ago`;
};

const refreshForm = useForm({});

const onRefresh = () => {
    info('Refreshing seller stats…');
    const action = refreshAction();
    refreshForm.submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: () => success('Seller stats refresh complete.'),
        onError: () => error('Seller stats refresh failed.'),
    });
};

const rawJson = computed(() => JSON.stringify(props.sellerStats.raw, null, 2));
</script>

<template>
    <section
        id="seller-stats"
        class="mt-12 scroll-mt-20"
        data-test="seller-stats-section"
    >
        <h2 class="mb-4 text-xl font-semibold text-foreground">
            Seller stats scraper
        </h2>

        <article
            :class="[
                'flex flex-col gap-4 rounded-lg border bg-card p-5',
                borderClass,
            ]"
            data-test="seller-stats-card"
        >
            <header class="flex items-start justify-between gap-3">
                <h3 class="text-lg font-semibold text-foreground">
                    Seller stats scraper
                </h3>
                <span
                    class="inline-flex items-center gap-2 text-sm font-medium"
                    :data-test="`status-${sellerStats.status.key}`"
                >
                    <i :class="iconClass" />
                    {{ sellerStats.status.label }}
                </span>
            </header>

            <p
                v-if="sellerStats.status.message"
                class="text-sm text-muted-foreground"
                data-test="status-message"
            >
                {{ sellerStats.status.message }}
            </p>
            <p
                v-if="
                    sellerStats.status.key === 'failed' &&
                    sellerStats.last_error
                "
                class="rounded-md bg-amber-500/10 p-2 text-xs text-amber-600 dark:text-amber-400"
                data-test="last-error"
            >
                {{ sellerStats.last_error }}
            </p>

            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted-foreground">Last successful</dt>
                    <dd class="font-medium text-foreground">
                        <MfDate
                            v-if="sellerStats.scraped_at"
                            :value="sellerStats.scraped_at"
                            format="datetime"
                        />
                        <span v-else>—</span>
                        <span class="ml-2 text-xs text-muted-foreground">
                            ({{ relativeFromNow(sellerStats.scraped_at) }})
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Last attempt</dt>
                    <dd class="font-medium text-foreground">
                        <MfDate
                            v-if="sellerStats.last_attempt_at"
                            :value="sellerStats.last_attempt_at"
                            format="datetime"
                        />
                        <span v-else>—</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Rating</dt>
                    <dd class="font-medium text-foreground">
                        {{ sellerStats.rating ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Reviews</dt>
                    <dd class="font-medium text-foreground">
                        {{ sellerStats.review_count ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Feedback comments</dt>
                    <dd class="font-medium text-foreground">
                        {{ sellerStats.feedback_count }}
                    </dd>
                </div>
            </dl>

            <div class="flex flex-col gap-2 sm:flex-row sm:gap-3">
                <button
                    type="button"
                    :disabled="sellerStats.refreshing || refreshForm.processing"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-mf-orange px-4 py-2 text-sm font-medium text-white hover:bg-mf-orange/90 disabled:opacity-50 sm:w-auto"
                    data-test="refresh-now"
                    @click="onRefresh"
                >
                    <i class="pi pi-refresh" />
                    Refresh now
                </button>
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted sm:w-auto"
                    data-test="view-raw-data"
                    @click="rawDialogVisible = true"
                >
                    <i class="pi pi-code" />
                    View raw data
                </button>
            </div>
        </article>

        <Dialog
            v-model:visible="rawDialogVisible"
            modal
            :draggable="false"
            header="Seller stats — raw data"
            :style="{ width: '100%', maxWidth: '40rem' }"
            :breakpoints="{ '768px': '100vw' }"
        >
            <pre
                class="max-h-96 overflow-auto rounded-md bg-muted p-3 text-xs text-foreground"
                data-test="raw-data-pre"
                >{{ rawJson }}</pre
            >
        </Dialog>
    </section>
</template>
