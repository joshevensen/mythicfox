<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MfMoney from '@/components/MfMoney.vue';

type SetStat = {
    name: string;
    cards_sold: number;
    revenue: number;
};

type RarityStat = {
    rarity: string;
    cards_sold: number;
    pct: number;
};

type GameStat = {
    game: string;
    total_revenue: number;
    cards_sold: number;
    avg_price_per_card: number | null;
    top_sets: SetStat[];
    rarity_mix: RarityStat[];
    avg_items_per_order: number | null;
    max_items_per_order: number | null;
};

defineProps<{
    gameStats: GameStat[];
}>();

function logoFor(game: string): string | null {
    const g = game.toLowerCase();

    if (g.includes('magic')) {
        return '/mtg.webp';
    }

    if (g.includes('lorcana')) {
        return '/lorcana.png';
    }

    if (g.includes('flesh')) {
        return '/fab.png';
    }

    return null;
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-[calc(100vh-8rem)] gap-4 overflow-x-auto pb-4">
        <div
            v-for="stat in gameStats"
            :key="stat.game"
            class="flex w-full shrink-0 flex-col gap-6 overflow-y-auto rounded-xl border border-border bg-card p-5 sm:w-90"
        >
            <img
                v-if="logoFor(stat.game)"
                :src="logoFor(stat.game)!"
                :alt="stat.game"
                class="mx-auto mb-4 h-20 w-auto object-contain object-left"
            />
            <span v-else class="text-base font-semibold text-foreground">
                {{ stat.game }}
            </span>

            <div class="flex flex-col gap-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Revenue
                </p>
                <p class="text-2xl font-bold text-foreground">
                    <MfMoney :cents="stat.total_revenue" align="left" />
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Cards Sold
                </p>
                <p class="text-2xl font-bold text-foreground tabular-nums">
                    {{ stat.cards_sold.toLocaleString() }}
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Avg Price / Card
                </p>
                <p class="text-2xl font-bold text-foreground">
                    <MfMoney :cents="stat.avg_price_per_card" align="left" />
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Avg Cards / Order
                </p>
                <p class="text-2xl font-bold text-foreground tabular-nums">
                    {{ stat.avg_items_per_order ?? '—' }}
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Max Cards / Order
                </p>
                <p class="text-2xl font-bold text-foreground tabular-nums">
                    {{ stat.max_items_per_order?.toLocaleString() ?? '—' }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Top Sets
                </p>
                <ol class="flex flex-col gap-2">
                    <li
                        v-for="(set, i) in stat.top_sets"
                        :key="set.name"
                        class="flex items-start gap-2"
                    >
                        <span
                            class="mt-0.5 w-4 shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ i + 1 }}.
                        </span>
                        <span
                            class="min-w-0 flex-1 text-sm leading-snug text-foreground"
                        >
                            {{ set.name }}
                        </span>
                        <span
                            class="shrink-0 text-sm text-muted-foreground tabular-nums"
                        >
                            <MfMoney :cents="set.revenue" align="right" />
                        </span>
                    </li>
                </ol>
            </div>

            <div class="flex flex-col gap-2">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Rarity Mix
                </p>
                <ul class="flex flex-col gap-1.5">
                    <li
                        v-for="r in stat.rarity_mix"
                        :key="r.rarity"
                        class="flex flex-col gap-1"
                    >
                        <div class="flex justify-between text-xs">
                            <span class="text-foreground">{{ r.rarity }}</span>
                            <span class="text-muted-foreground tabular-nums">
                                {{ r.pct }}%
                            </span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-muted">
                            <div
                                class="h-1.5 rounded-full bg-mf-orange"
                                :style="{ width: `${r.pct}%` }"
                            />
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div
            v-if="gameStats.length === 0"
            class="flex items-center justify-center text-sm text-muted-foreground"
        >
            No order data yet.
        </div>
    </div>
</template>
