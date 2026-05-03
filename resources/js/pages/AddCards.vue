<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import Select from 'primevue/select';
import { computed, ref, watch } from 'vue';
import { store as storeAction } from '@/actions/App/Http/Controllers/AddCardsController';
import MfErrorBanner from '@/components/MfErrorBanner.vue';
import MfPageHeader from '@/components/MfPageHeader.vue';
import MfQtyInput from '@/components/MfQtyInput.vue';
import { useMfToast } from '@/composables/useMfToast';
import { addCards as addCardsRoute } from '@/routes';

type Product = { id: number; name: string };
type SetOption = { id: number; name: string };
type CardRow = { id: number; name: string; number: string };

type Scope = {
    product_id: number | null;
    set_id: number | null;
    condition: string | null;
};

const props = defineProps<{
    products: Product[];
    sets: SetOption[];
    cards: CardRow[];
    conditions: string[];
    scope: Scope;
}>();

const { success, info } = useMfToast();

const productId = ref<number | null>(props.scope.product_id);
const setId = ref<number | null>(props.scope.set_id);
const condition = ref<string | null>(props.scope.condition);

const conditionOptions = computed(() =>
    props.conditions.map((c) => ({ value: c, label: c })),
);

const productOptions = computed(() =>
    props.products.map((p) => ({ value: p.id, label: p.name })),
);

const setOptions = computed(() =>
    props.sets.map((s) => ({ value: s.id, label: s.name })),
);

const quantities = ref<Record<number, number>>({});

watch(
    () => props.cards,
    (cards) => {
        const next: Record<number, number> = {};

        for (const card of cards) {
            next[card.id] = 0;
        }

        quantities.value = next;
    },
    { immediate: true },
);

const totalCount = computed(() =>
    Object.values(quantities.value).reduce((sum, q) => sum + (q || 0), 0),
);

const hasPending = computed(() => totalCount.value > 0);

const allScoped = computed(
    () =>
        productId.value !== null &&
        setId.value !== null &&
        condition.value !== null,
);

const errorMessage = ref<string | null>(null);
const saving = ref(false);

const reloadScope = (next: {
    product_id?: number | null;
    set_id?: number | null;
    condition?: string | null;
}) => {
    const url = addCardsRoute({
        query: {
            product_id: next.product_id ?? undefined,
            set_id: next.set_id ?? undefined,
            condition: next.condition ?? undefined,
        },
    }).url;

    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'sets', 'cards', 'scope'],
        },
    );
};

const buildEntries = () =>
    Object.entries(quantities.value)
        .filter(([, qty]) => qty > 0)
        .map(([cardId, qty]) => ({
            card_id: Number(cardId),
            qty,
        }));

const performSave = (
    options: { onComplete?: () => void; onError?: () => void } = {},
) => {
    if (!allScoped.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    const action = storeAction();
    const form = useForm({
        product_id: productId.value,
        set_id: setId.value,
        condition: condition.value,
        entries: buildEntries(),
    });

    form.submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = (page.props as { flash?: { count?: number } }).flash;
            const count = flash?.count ?? totalCount.value;
            success(
                `Added ${count} cards to ${currentSetName.value} (${condition.value}).`,
            );

            for (const key of Object.keys(quantities.value)) {
                quantities.value[Number(key)] = 0;
            }

            options.onComplete?.();
        },
        onError: () => {
            errorMessage.value = "Couldn't save the entered cards. Try again.";
            options.onError?.();
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};

const currentSetName = computed(() => {
    const found = props.sets.find((s) => s.id === setId.value);

    return found?.name ?? '';
});

const onSave = () => performSave();

const handleProductChange = (next: number | null) => {
    if (hasPending.value && allScoped.value) {
        const targetProduct = next;
        performSave({
            onComplete: () => {
                info('Saved before switching.');
                productId.value = targetProduct;
                setId.value = null;
                condition.value = null;
                reloadScope({
                    product_id: targetProduct,
                    set_id: null,
                    condition: null,
                });
            },
            onError: () => {
                productId.value = props.scope.product_id;
            },
        });

        return;
    }

    productId.value = next;
    setId.value = null;
    condition.value = null;
    reloadScope({ product_id: next, set_id: null, condition: null });
};

const handleSetChange = (next: number | null) => {
    if (hasPending.value && allScoped.value) {
        const targetSet = next;
        performSave({
            onComplete: () => {
                info('Saved before switching.');
                setId.value = targetSet;
                reloadScope({
                    product_id: productId.value,
                    set_id: targetSet,
                    condition: condition.value,
                });
            },
            onError: () => {
                setId.value = props.scope.set_id;
            },
        });

        return;
    }

    setId.value = next;
    reloadScope({
        product_id: productId.value,
        set_id: next,
        condition: condition.value,
    });
};

const handleConditionChange = (next: string | null) => {
    if (hasPending.value && allScoped.value) {
        const targetCondition = next;
        performSave({
            onComplete: () => {
                info('Saved before switching.');
                condition.value = targetCondition;
                reloadScope({
                    product_id: productId.value,
                    set_id: setId.value,
                    condition: targetCondition,
                });
            },
            onError: () => {
                condition.value = props.scope.condition;
            },
        });

        return;
    }

    condition.value = next;
    reloadScope({
        product_id: productId.value,
        set_id: setId.value,
        condition: next,
    });
};

const saveLabel = computed(() =>
    hasPending.value ? `Save ${totalCount.value} cards` : 'Save',
);
</script>

<template>
    <Head title="Add Cards" />
    <MfPageHeader title="Add Cards" />

    <MfErrorBanner v-if="errorMessage" :message="errorMessage" />

    <div
        class="grid grid-cols-1 gap-3 sm:grid-cols-3"
        data-test="add-cards-scope"
    >
        <Select
            :model-value="productId"
            :options="productOptions"
            option-label="label"
            option-value="value"
            placeholder="Product"
            class="w-full"
            data-test="scope-product"
            @update:model-value="handleProductChange"
        />
        <Select
            :model-value="setId"
            :options="setOptions"
            option-label="label"
            option-value="value"
            placeholder="Set"
            class="w-full"
            :disabled="productId === null"
            data-test="scope-set"
            @update:model-value="handleSetChange"
        />
        <Select
            :model-value="condition"
            :options="conditionOptions"
            option-label="label"
            option-value="value"
            placeholder="Condition"
            class="w-full"
            data-test="scope-condition"
            @update:model-value="handleConditionChange"
        />
    </div>

    <div class="mt-6 pb-24" data-test="add-cards-list">
        <p
            v-if="!allScoped"
            class="rounded-md border border-dashed border-border p-6 text-center text-muted-foreground"
            data-test="scope-placeholder"
        >
            Pick a product, set, and condition to add cards.
        </p>

        <p
            v-else-if="cards.length === 0"
            class="rounded-md border border-dashed border-border p-6 text-center text-muted-foreground"
            data-test="scope-no-matches"
        >
            No cards in {{ currentSetName }} match {{ condition }}. Try a
            different condition.
        </p>

        <ul v-else class="flex flex-col gap-2">
            <li
                v-for="card in cards"
                :key="card.id"
                :class="[
                    'flex items-center justify-between gap-3 rounded-md border border-border p-3',
                    quantities[card.id] > 0 ? 'bg-emerald-500/5' : 'bg-card',
                ]"
                :data-test="`card-row-${card.id}`"
            >
                <div class="flex flex-col">
                    <span class="text-sm font-medium text-foreground">
                        {{ card.name }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        #{{ card.number }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <i
                        v-if="quantities[card.id] > 0"
                        class="pi pi-check text-emerald-500"
                        aria-hidden="true"
                    />
                    <MfQtyInput
                        v-model="quantities[card.id]"
                        :input-id="`qty-${card.id}`"
                        :min="0"
                    />
                </div>
            </li>
        </ul>
    </div>

    <div
        class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 p-3 backdrop-blur"
        style="overscroll-behavior: contain"
    >
        <button
            type="button"
            class="w-full rounded-md bg-mf-orange px-4 py-3 text-base font-semibold text-white hover:bg-mf-orange/90 disabled:opacity-50"
            :disabled="!hasPending || saving"
            data-test="add-cards-save"
            @click="onSave"
        >
            {{ saveLabel }}
        </button>
    </div>
</template>
