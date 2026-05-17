<?php

namespace App\Services\Orders;

use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Splits an order's items into PWE (plain white envelope) groups, minimising
 * total envelope count (and therefore postage cost) via a First-Fit Decreasing
 * bin-packing heuristic.
 *
 * Each returned group is sorted by product_line → set_name → product_name
 * ready for display on a packing-slip sheet.
 *
 * Constraints per envelope
 * ────────────────────────
 *   • ≤ MAX_ROWS    line items  (fits on one printed page)
 *   • ≤ MAX_CARDS   physical cards  (3 shields × 10 cards)
 *   • ≤ MAX_GRAMS   total weight    (USPS 2 oz letter limit)
 */
class PackingSlipSplitter
{
    // ── Packaging weights (grams) ─────────────────────────────────
    private const ENVELOPE     = 5.68;
    private const SHIELD       = 5.15;
    private const PENNY_SLEEVE = 0.60;   // fits up to 5 cards
    private const OUTER_SLEEVE = 1.22;   // 1 card per sleeve (cards > $2.00)
    private const NON_FOIL     = 1.84;
    private const FOIL         = 1.98;

    // ── Per-envelope constraints ──────────────────────────────────
    public const MAX_ROWS  = 20;            // line items per printed sheet
    public const MAX_CARDS = 30;            // physical cards (3 shields × 10)
    public const MAX_GRAMS = 99.22;         // USPS first-class letter ceiling: 3.5 oz

    // ── Sleeve-type threshold ─────────────────────────────────────
    private const OUTER_SLEEVE_CENTS = 200; // unit_price > $2.00 → outer sleeve

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return list<list<OrderItem>>  Each inner list is one envelope's items, sorted for display.
     */
    public function split(Collection $items): array
    {
        // Pre-compute total weight per set so we can sort heaviest sets first (FFD).
        $setKey = fn (OrderItem $item): string => $item->product_line . '|' . ($item->set_name ?? '');

        $setWeights = $items
            ->groupBy($setKey)
            ->map(fn ($group) => $group->sum(fn (OrderItem $i) => $this->itemWeight($i)));

        // Sort: heaviest set first → within the same set, heaviest item first.
        $sorted = $items->sort(function (OrderItem $a, OrderItem $b) use ($setKey, $setWeights): int {
            $bySetWeight = $setWeights[$setKey($b)] <=> $setWeights[$setKey($a)];
            if ($bySetWeight !== 0) {
                return $bySetWeight;
            }

            $bySetName = strcmp($setKey($a), $setKey($b));
            if ($bySetName !== 0) {
                return $bySetName;
            }

            return $this->itemWeight($b) <=> $this->itemWeight($a);
        })->values();

        $bins     = [];   // list<list<OrderItem>>
        $binCards = [];   // physical card count per bin
        $binRows  = [];   // line-item count per bin
        $binSets  = [];   // set keys present in each bin (for affinity ordering)

        foreach ($sorted as $item) {
            $key    = $setKey($item);
            $placed = false;

            // Prefer bins that already contain this set, then fall back to others.
            $preferred = array_keys(array_filter($binSets, fn ($s) => in_array($key, $s, true)));
            $others    = array_keys(array_filter($binSets, fn ($s) => ! in_array($key, $s, true)));

            foreach ([...$preferred, ...$others] as $i) {
                if ($this->fits($bins[$i], $binCards[$i], $binRows[$i], $item)) {
                    $bins[$i][]    = $item;
                    $binCards[$i] += $item->quantity;
                    $binRows[$i]++;
                    if (! in_array($key, $binSets[$i], true)) {
                        $binSets[$i][] = $key;
                    }
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $bins[]     = [$item];
                $binCards[] = $item->quantity;
                $binRows[]  = 1;
                $binSets[]  = [$key];
            }
        }

        return array_map(fn (array $bin) => collect($bin)
            ->sortBy(['product_line', 'set_name', 'product_name'])
            ->values()
            ->all(), $bins);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function fits(array $bin, int $currentCards, int $currentRows, OrderItem $item): bool
    {
        if ($currentCards + $item->quantity > self::MAX_CARDS) {
            return false;
        }

        if ($currentRows + 1 > self::MAX_ROWS) {
            return false;
        }

        return $this->binWeight([...$bin, $item]) <= self::MAX_GRAMS;
    }

    /**
     * Total weight of an envelope containing the given items.
     *
     * Penny sleeves are shared across ALL cheap cards in the bin (5 cards per
     * sleeve), so sleeve weight is computed on the bin-level totals rather than
     * per line item.
     */
    private function binWeight(array $items): float
    {
        $totalCards = 0;
        $cheapCards = 0;
        $expCards   = 0;
        $cardWeight = 0.0;

        foreach ($items as $item) {
            $isFoil      = stripos($item->product_name ?? '', 'foil') !== false;
            $isExpensive = ($item->unit_price ?? 0) > self::OUTER_SLEEVE_CENTS;

            $totalCards += $item->quantity;
            $cardWeight += $item->quantity * ($isFoil ? self::FOIL : self::NON_FOIL);

            if ($isExpensive) {
                $expCards += $item->quantity;
            } else {
                $cheapCards += $item->quantity;
            }
        }

        $shields      = (int) ceil($totalCards / 10);
        $sleeveWeight = $expCards * self::OUTER_SLEEVE
                      + (int) ceil($cheapCards / 5) * self::PENNY_SLEEVE;

        return self::ENVELOPE + $shields * self::SHIELD + $cardWeight + $sleeveWeight;
    }

    /**
     * Per-item weight estimate used only for FFD sort ordering.
     * Uses per-card penny-sleeve fraction (0.12 g) rather than the ceil grouping
     * so that heavier items sort first without depending on bin context.
     */
    private function itemWeight(OrderItem $item): float
    {
        $isFoil      = stripos($item->product_name ?? '', 'foil') !== false;
        $isExpensive = ($item->unit_price ?? 0) > self::OUTER_SLEEVE_CENTS;

        $cardWeight   = $item->quantity * ($isFoil ? self::FOIL : self::NON_FOIL);
        $sleeveWeight = $isExpensive
            ? $item->quantity * self::OUTER_SLEEVE
            : $item->quantity * (self::PENNY_SLEEVE / 5);

        return $cardWeight + $sleeveWeight;
    }
}
