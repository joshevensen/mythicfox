<script setup lang="ts">
import { computed } from 'vue';

type CardShape = {
    name: string;
    number: string | null;
    set?: string | null;
    set_name?: string | null;
    condition?: string | null;
    rarity?: string | null;
};

type OrderItemShape = {
    product_name: string;
    card_number?: string | null;
    set_name?: string | null;
    condition?: string | null;
    rarity?: string | null;
};

type Props = {
    card?: CardShape;
    orderItem?: OrderItemShape;
    compact?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    card: undefined,
    orderItem: undefined,
    compact: false,
});

type Identity = {
    name: string;
    number: string | null;
    set: string | null;
    condition: string | null;
    rarity: string | null;
};

const identity = computed<Identity>(() => {
    if (props.card) {
        return {
            name: props.card.name,
            number: props.card.number ?? null,
            set: props.card.set ?? props.card.set_name ?? null,
            condition: props.card.condition ?? null,
            rarity: props.card.rarity ?? null,
        };
    }

    const item = props.orderItem;

    return {
        name: item?.product_name ?? '',
        number: item?.card_number ?? null,
        set: item?.set_name ?? null,
        condition: item?.condition ?? null,
        rarity: item?.rarity ?? null,
    };
});
</script>

<template>
    <div v-if="compact" class="flex items-baseline gap-1">
        <span class="font-medium text-foreground">{{ identity.name }}</span>
        <span v-if="identity.number" class="text-muted-foreground"
            >#{{ identity.number }}</span
        >
    </div>
    <div v-else class="flex flex-col">
        <div class="flex flex-wrap items-baseline gap-1.5 text-sm">
            <span class="font-medium text-foreground">{{ identity.name }}</span>
            <span v-if="identity.number" class="text-slate-400">·</span>
            <span v-if="identity.number" class="text-muted-foreground"
                >#{{ identity.number }}</span
            >
            <span v-if="identity.set" class="text-slate-400">·</span>
            <span v-if="identity.set" class="text-muted-foreground">{{
                identity.set
            }}</span>
        </div>
        <div
            v-if="identity.condition || identity.rarity"
            class="flex items-center justify-between text-xs text-muted-foreground"
        >
            <span>{{ identity.condition ?? '' }}</span>
            <span>{{ identity.rarity ?? '' }}</span>
        </div>
    </div>
</template>
