<script setup lang="ts">
import { onMounted, ref } from 'vue';
import MfMoney from '@/components/MfMoney.vue';
import orderItemsRoutes from '@/routes/orders/items';

type OrderItem = {
    id: number;
    product_line: string | null;
    set_name: string | null;
    product_name: string | null;
    number: string | null;
    condition: string | null;
    quantity: number;
    unit_price: number | null;
    total_price: number | null;
};

const props = defineProps<{ orderNumber: string }>();

const loading = ref(true);
const items = ref<OrderItem[]>([]);
const error = ref<string | null>(null);

onMounted(async () => {
    try {
        const res = await fetch(orderItemsRoutes.index.url(props.orderNumber));

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = (await res.json()) as { data: OrderItem[] };
        items.value = json.data;
    } catch {
        error.value = 'Failed to load items.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="px-4 py-3">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <template v-else>
            <div
                v-if="loading"
                class="flex flex-col gap-1"
            >
                <div
                    v-for="n in 3"
                    :key="n"
                    class="h-6 animate-pulse rounded bg-muted"
                />
            </div>

            <p
                v-else-if="items.length === 0"
                class="text-sm text-muted-foreground"
            >
                No items found.
            </p>

            <table
                v-else
                class="w-full text-sm"
            >
                <thead>
                    <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                        <th class="pb-1 pr-4">Product Line</th>
                        <th class="pb-1 pr-4">Set</th>
                        <th class="pb-1 pr-4">Card</th>
                        <th class="pb-1 pr-4">#</th>
                        <th class="pb-1 pr-4">Condition</th>
                        <th class="pb-1 pr-4 text-right">Qty</th>
                        <th class="pb-1 pr-4 text-right">Unit Price</th>
                        <th class="pb-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-border/50 last:border-0"
                    >
                        <td class="py-1 pr-4 text-muted-foreground">{{ item.product_line ?? '—' }}</td>
                        <td class="py-1 pr-4">{{ item.set_name ?? '—' }}</td>
                        <td class="py-1 pr-4 font-medium">{{ item.product_name ?? '—' }}</td>
                        <td class="py-1 pr-4 text-muted-foreground">{{ item.number ?? '—' }}</td>
                        <td class="py-1 pr-4 text-muted-foreground">{{ item.condition ?? '—' }}</td>
                        <td class="py-1 pr-4 text-right tabular-nums">{{ item.quantity }}</td>
                        <td class="py-1 pr-4 text-right">
                            <MfMoney :cents="item.unit_price" align="right" />
                        </td>
                        <td class="py-1 text-right">
                            <MfMoney :cents="item.total_price" align="right" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>
    </div>
</template>
