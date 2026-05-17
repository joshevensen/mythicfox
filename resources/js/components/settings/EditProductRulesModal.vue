<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import { ref, watch } from 'vue';
import { updateProduct as updateProductAction } from '@/actions/App/Http/Controllers/Settings/PricingRulesController';
import MfMoneyInput from '@/components/MfMoneyInput.vue';
import { useMfToast } from '@/composables/useMfToast';

type Product = {
    id: number;
    name: string;
    base_price: number;
    high_price: number;
    market_offset: number;
    high_offset: number;
};

const props = defineProps<{
    product: Product | null;
    visible: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void;
    (e: 'saved'): void;
}>();

const { success } = useMfToast();

const form = useForm({
    base_price: 0,
    high_price: 0,
    market_offset: 0,
    high_offset: 0,
});

watch(
    () => props.product,
    (next) => {
        if (next) {
            form.defaults({
                base_price: next.base_price,
                high_price: next.high_price,
                market_offset: next.market_offset,
                high_offset: next.high_offset,
            });
            form.reset();
        }
    },
    { immediate: true },
);

const close = () => emit('update:visible', false);

const submit = () => {
    if (!props.product) {
        return;
    }

    const action = updateProductAction(props.product.id);

    form.transform((data) => ({
        base_price: data.base_price ?? 0,
        high_price: data.high_price ?? 0,
        market_offset: data.market_offset ?? 0,
        high_offset: data.high_offset ?? 0,
    })).submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: () => {
            success(`${props.product?.name ?? ''} pricing rules saved.`);
            emit('saved');
            close();
        },
    });
};

const dialogVisible = ref(props.visible);
watch(
    () => props.visible,
    (v) => (dialogVisible.value = v),
);
watch(dialogVisible, (v) => emit('update:visible', v));
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :draggable="false"
        :header="product ? `${product.name} — pricing rules` : 'Pricing rules'"
        :style="{ width: '100%', maxWidth: '32rem' }"
        :breakpoints="{ '768px': '100vw' }"
    >
        <form
            class="flex flex-col gap-4"
            data-test="edit-product-rules-form"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-2">
                <label
                    for="product-base-price"
                    class="text-sm font-medium text-foreground"
                >
                    Base price
                </label>
                <MfMoneyInput
                    v-model="form.base_price"
                    input-id="product-base-price"
                />
                <p
                    v-if="form.errors.base_price"
                    class="text-sm text-red-500"
                    data-test="error-base-price"
                >
                    {{ form.errors.base_price }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label
                    for="product-high-price"
                    class="text-sm font-medium text-foreground"
                >
                    High price
                </label>
                <MfMoneyInput
                    v-model="form.high_price"
                    input-id="product-high-price"
                />
                <p v-if="form.errors.high_price" class="text-sm text-red-500">
                    {{ form.errors.high_price }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label
                    for="product-market-offset"
                    class="text-sm font-medium text-foreground"
                >
                    Market offset
                </label>
                <MfMoneyInput
                    v-model="form.market_offset"
                    input-id="product-market-offset"
                />
                <p
                    v-if="form.errors.market_offset"
                    class="text-sm text-red-500"
                >
                    {{ form.errors.market_offset }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label
                    for="product-high-offset"
                    class="text-sm font-medium text-foreground"
                >
                    High offset
                </label>
                <MfMoneyInput
                    v-model="form.high_offset"
                    input-id="product-high-offset"
                />
                <p v-if="form.errors.high_offset" class="text-sm text-red-500">
                    {{ form.errors.high_offset }}
                </p>
            </div>

            <div class="mt-2 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted"
                    @click="close"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-mf-orange px-4 py-2 text-sm font-medium text-white hover:bg-mf-orange/90 disabled:opacity-50"
                    data-test="submit-product-rules"
                >
                    Save
                </button>
            </div>
        </form>
    </Dialog>
</template>
