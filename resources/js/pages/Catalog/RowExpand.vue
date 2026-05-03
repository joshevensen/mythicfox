<script setup lang="ts">
import MfMonospaceId from '@/components/MfMonospaceId.vue';

type Variant = {
    condition: string;
    quantity: number;
    tcgplayer_id: number;
};

defineProps<{
    variants: Variant[];
}>();
</script>

<template>
    <div data-test="catalog-expand">
        <div
            v-if="variants.length === 0"
            class="px-4 py-3 text-sm text-muted-foreground"
        >
            No condition variants on file.
        </div>
        <table v-else class="w-full text-sm">
            <thead class="text-xs text-muted-foreground">
                <tr>
                    <th class="px-4 py-2 text-left">Condition</th>
                    <th class="px-4 py-2 text-right">Quantity</th>
                    <th class="px-4 py-2 text-left">TCGplayer ID</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="v in variants"
                    :key="v.tcgplayer_id"
                    class="border-t border-border"
                    :data-test="`catalog-variant-${v.tcgplayer_id}`"
                >
                    <td class="px-4 py-2">{{ v.condition }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">
                        {{ v.quantity }}
                    </td>
                    <td class="px-4 py-2">
                        <MfMonospaceId :value="v.tcgplayer_id" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
