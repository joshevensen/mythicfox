<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import { computed, ref, watch } from 'vue';
import { updateSet as updateSetAction } from '@/actions/App/Http/Controllers/Settings/PricingRulesController';
import MfMoneyInput from '@/components/MfMoneyInput.vue';
import { useMfConfirm } from '@/composables/useMfConfirm';
import { useMfToast } from '@/composables/useMfToast';
import { useMoney } from '@/composables/useMoney';

type Product = {
    id: number;
    name: string;
    base_price: number;
    high_price: number;
    market_offset: number;
    high_offset: number;
};

type SetRow = {
    id: number;
    name: string;
    base_price: number | null;
    high_price: number | null;
    market_offset: number | null;
    high_offset: number | null;
};

type SetRuleField =
    | 'base_price'
    | 'high_price'
    | 'market_offset'
    | 'high_offset';

type SetRulesForm = Record<SetRuleField, number | null>;

const props = defineProps<{
    set: SetRow | null;
    product: Product | null;
    visible: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void;
    (e: 'saved'): void;
}>();

const { success } = useMfToast();
const { confirm } = useMfConfirm();
const { formatCents } = useMoney();

const form = useForm<SetRulesForm>({
    base_price: null,
    high_price: null,
    market_offset: null,
    high_offset: null,
});

watch(
    () => props.set,
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
    if (!props.set) {
        return;
    }

    const action = updateSetAction(props.set.id);

    form.submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: () => {
            success(`${props.set?.name ?? ''} pricing rules saved.`);
            emit('saved');
            close();
        },
    });
};

const inheritField = (field: SetRuleField) => {
    form[field] = null;
};

const resetAll = () => {
    confirm({
        title: 'Reset all to product defaults?',
        body: `All four fields will be cleared so this set inherits from ${props.product?.name ?? 'its product'}.`,
        verb: 'Reset',
        destructive: true,
        onConfirm: () => {
            form.base_price = null;
            form.high_price = null;
            form.market_offset = null;
            form.high_offset = null;
        },
    });
};

const productDefaultLabel = (field: SetRuleField) =>
    props.product
        ? `${props.product.name} default: ${formatCents(props.product[field])}`
        : '';

const subtitle = computed(() =>
    props.product ? `Overrides ${props.product.name} defaults` : '',
);

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
        :header="set ? `${set.name} — pricing rules` : 'Set pricing rules'"
        :style="{ width: '100%', maxWidth: '32rem' }"
        :breakpoints="{ '768px': '100vw' }"
    >
        <template #header>
            <div class="flex flex-col gap-1">
                <span class="text-lg font-semibold">
                    {{ set?.name ?? '' }} — pricing rules
                </span>
                <span class="text-sm text-muted-foreground">
                    {{ subtitle }}
                </span>
            </div>
        </template>

        <form
            class="flex flex-col gap-4"
            data-test="edit-set-rules-form"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <label
                        for="set-base-price"
                        class="text-sm font-medium text-foreground"
                    >
                        Base price
                    </label>
                    <button
                        type="button"
                        class="text-xs text-mf-orange hover:underline"
                        data-test="inherit-base_price"
                        @click="inheritField('base_price')"
                    >
                        ↺ inherit
                    </button>
                </div>
                <MfMoneyInput
                    v-model="form.base_price"
                    input-id="set-base-price"
                    nullable
                />
                <p class="text-xs text-muted-foreground">
                    {{ productDefaultLabel('base_price') }}
                </p>
                <p v-if="form.errors.base_price" class="text-sm text-red-500">
                    {{ form.errors.base_price }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <label
                        for="set-high-price"
                        class="text-sm font-medium text-foreground"
                    >
                        High price
                    </label>
                    <button
                        type="button"
                        class="text-xs text-mf-orange hover:underline"
                        data-test="inherit-high_price"
                        @click="inheritField('high_price')"
                    >
                        ↺ inherit
                    </button>
                </div>
                <MfMoneyInput
                    v-model="form.high_price"
                    input-id="set-high-price"
                    nullable
                />
                <p class="text-xs text-muted-foreground">
                    {{ productDefaultLabel('high_price') }}
                </p>
                <p v-if="form.errors.high_price" class="text-sm text-red-500">
                    {{ form.errors.high_price }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <label
                        for="set-market-offset"
                        class="text-sm font-medium text-foreground"
                    >
                        Market offset
                    </label>
                    <button
                        type="button"
                        class="text-xs text-mf-orange hover:underline"
                        data-test="inherit-market_offset"
                        @click="inheritField('market_offset')"
                    >
                        ↺ inherit
                    </button>
                </div>
                <MfMoneyInput
                    v-model="form.market_offset"
                    input-id="set-market-offset"
                    nullable
                />
                <p class="text-xs text-muted-foreground">
                    {{ productDefaultLabel('market_offset') }}
                </p>
                <p
                    v-if="form.errors.market_offset"
                    class="text-sm text-red-500"
                >
                    {{ form.errors.market_offset }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <label
                        for="set-high-offset"
                        class="text-sm font-medium text-foreground"
                    >
                        High offset
                    </label>
                    <button
                        type="button"
                        class="text-xs text-mf-orange hover:underline"
                        data-test="inherit-high_offset"
                        @click="inheritField('high_offset')"
                    >
                        ↺ inherit
                    </button>
                </div>
                <MfMoneyInput
                    v-model="form.high_offset"
                    input-id="set-high-offset"
                    nullable
                />
                <p class="text-xs text-muted-foreground">
                    {{ productDefaultLabel('high_offset') }}
                </p>
                <p v-if="form.errors.high_offset" class="text-sm text-red-500">
                    {{ form.errors.high_offset }}
                </p>
            </div>

            <button
                type="button"
                class="self-start text-sm text-red-500 hover:underline"
                data-test="reset-all-set-rules"
                @click="resetAll"
            >
                Reset all to product defaults
            </button>

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
                    data-test="submit-set-rules"
                >
                    Save
                </button>
            </div>
        </form>
    </Dialog>
</template>
