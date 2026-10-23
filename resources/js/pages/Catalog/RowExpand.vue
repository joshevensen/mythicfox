<script setup lang="ts">
import MfMoney from '@/components/MfMoney.vue';
import MfMonospaceId from '@/components/MfMonospaceId.vue';

type Variant = {
    finish: string;
    tcgplayer_id: number | null;
    nm_market_price: number | null;
    nm_low_price: number | null;
    lp_market_price: number | null;
    lp_low_price: number | null;
    mp_market_price: number | null;
    mp_low_price: number | null;
};

type PriceField = Exclude<keyof Variant, 'finish' | 'tcgplayer_id'>;

const priceColumns: { field: PriceField; label: string }[] = [
    { field: 'nm_market_price', label: 'NM Market' },
    { field: 'nm_low_price', label: 'NM Low' },
    { field: 'lp_market_price', label: 'LP Market' },
    { field: 'lp_low_price', label: 'LP Low' },
    { field: 'mp_market_price', label: 'MP Market' },
    { field: 'mp_low_price', label: 'MP Low' },
];

defineProps<{
    variants: Variant[];
}>();

const labelForFinish = (finish: string): string =>
    finish
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
</script>

<template>
    <div data-test="catalog-expand">
        <div
            v-if="variants.length === 0"
            class="px-4 py-3 text-sm text-muted-foreground"
        >
            No printings on file.
        </div>
        <table v-else class="w-full text-sm">
            <thead class="text-xs text-muted-foreground">
                <tr>
                    <th class="px-4 py-2 text-left">Finish</th>
                    <th class="px-4 py-2 text-left">TCGplayer ID</th>
                    <th
                        v-for="col in priceColumns"
                        :key="col.field"
                        class="px-4 py-2 text-right"
                    >
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="v in variants"
                    :key="v.finish"
                    class="border-t border-border"
                    :data-test="`catalog-variant-${v.finish}`"
                >
                    <td class="px-4 py-2">{{ labelForFinish(v.finish) }}</td>
                    <td class="px-4 py-2">
                        <MfMonospaceId
                            v-if="v.tcgplayer_id !== null"
                            :value="v.tcgplayer_id"
                        />
                        <span v-else class="text-muted-foreground">—</span>
                    </td>
                    <td
                        v-for="col in priceColumns"
                        :key="col.field"
                        class="px-4 py-2 text-right"
                    >
                        <MfMoney :cents="v[col.field]" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
