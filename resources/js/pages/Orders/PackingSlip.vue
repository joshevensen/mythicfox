<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type OrderSummary = {
    id: number;
    tcgplayer_order_number: string;
    buyer_name: string;
    order_date: string | null;
};

defineProps<{
    orders: OrderSummary[];
    placeholder_message: string;
}>();
</script>

<template>
    <Head title="Packing slip" />

    <main class="mx-auto max-w-3xl px-6 py-10">
        <h1 class="text-xl font-semibold">Packing slips</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            {{ placeholder_message }}
        </p>

        <ul
            class="mt-6 divide-y divide-border rounded-md border border-border"
            data-test="packing-slip-orders"
        >
            <li
                v-for="order in orders"
                :key="order.id"
                class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                :data-test="`packing-slip-order-${order.tcgplayer_order_number}`"
            >
                <span class="font-mono">{{
                    order.tcgplayer_order_number
                }}</span>
                <span class="text-muted-foreground">{{
                    order.buyer_name
                }}</span>
            </li>
        </ul>
    </main>
</template>
