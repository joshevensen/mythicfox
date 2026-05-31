<?php

namespace App\Services\Catalog\Sync;

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Support\Facades\Http;

class FleshAndBloodSource implements CatalogSyncSource
{
    /** S=Standard, R=Rainbow Foil, C=Cold Foil, G=Gold Cold Foil */
    private const FOILING_MAP = [
        'S' => 'non-foil',
        'R' => 'rainbow-foil',
        'C' => 'cold-foil',
        'G' => 'gold-cold-foil',
    ];

    /** @var array<string, string> name → set code */
    private array $nameToCode = [];

    public function syncSets(Product $product): int
    {
        $sets = Http::get($this->baseUrl('sets.json'))->throw()->json() ?? [];
        $count = 0;

        foreach ($sets as $set) {
            $name = (string) $set['name'];
            $code = (string) $set['id'];

            Set::upsert(
                [['product_id' => $product->id, 'name' => $name]],
                ['product_id', 'name'],
                [],
            );

            $this->nameToCode[$name] = $code;
            $count++;
        }

        return $count;
    }

    public function syncCardsForSet(Set $set): int
    {
        $code = $this->resolveCode($set->name);

        if ($code === null) {
            return 0;
        }

        $fabCards = Http::get($this->baseUrl("{$code}.json"))->throw()->json() ?? [];

        $cardRows = [];
        $printingMeta = [];

        foreach ($fabCards as $card) {
            $name = (string) $card['name'];
            $printings = (array) ($card['printings'] ?? []);

            foreach ($printings as $printing) {
                if ((string) $printing['set_id'] !== $code) {
                    continue;
                }

                $number = (string) $printing['id'];
                $rarity = (string) ($printing['rarity'] ?? '');
                $foiling = (string) ($printing['foiling'] ?? 'S');
                $finish = self::FOILING_MAP[$foiling] ?? 'non-foil';
                $uniqueId = (string) ($printing['unique_id'] ?? '');
                $imageUrl = $printing['image_url'] !== '' ? (string) $printing['image_url'] : null;

                $tcgplayerId = null;
                if (! empty($printing['tcgplayer_product_id'])) {
                    $tcgplayerId = (int) $printing['tcgplayer_product_id'];
                }

                $cardRows[$name."\0".$number] = [
                    'set_id' => $set->id,
                    'name' => $name,
                    'number' => $number,
                    'rarity' => $rarity,
                ];

                $printingMeta[] = [
                    'name' => $name,
                    'number' => $number,
                    'finish' => $finish,
                    'tcgplayer_id' => $tcgplayerId,
                    'image_url' => $imageUrl,
                    'fab_id' => $uniqueId,
                ];
            }
        }

        if ($cardRows !== []) {
            Card::upsert(array_values($cardRows), ['set_id', 'name', 'number'], ['rarity']);
        }

        if ($printingMeta !== []) {
            $this->upsertPrintings($set->id, $printingMeta);
        }

        return count($cardRows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function upsertPrintings(int $setId, array $rows): void
    {
        $names = array_unique(array_column($rows, 'name'));
        $numbers = array_unique(array_column($rows, 'number'));

        $cards = Card::query()
            ->where('set_id', $setId)
            ->whereIn('name', $names)
            ->whereIn('number', $numbers)
            ->get(['id', 'name', 'number'])
            ->keyBy(fn (Card $c) => $c->name."\0".$c->number);

        $printings = [];

        foreach ($rows as $row) {
            $card = $cards[$row['name']."\0".$row['number']] ?? null;

            if ($card === null) {
                continue;
            }

            $otherIds = $row['fab_id'] !== '' ? ['fab_id' => $row['fab_id']] : null;

            $printings[] = [
                'card_id' => $card->id,
                'finish' => $row['finish'],
                'tcgplayer_id' => $row['tcgplayer_id'],
                'image_url' => $row['image_url'],
                'justtcg_id' => null,
                'other_ids' => $otherIds !== null ? json_encode($otherIds) : null,
                'market_price' => null,
                'low_price' => null,
            ];
        }

        if ($printings !== []) {
            Printing::upsert(
                $printings,
                ['card_id', 'finish'],
                ['tcgplayer_id', 'image_url', 'other_ids'],
            );
        }
    }

    private function resolveCode(string $setName): ?string
    {
        if (isset($this->nameToCode[$setName])) {
            return $this->nameToCode[$setName];
        }

        $sets = Http::get($this->baseUrl('sets.json'))->throw()->json() ?? [];

        foreach ($sets as $set) {
            if ($set['name'] === $setName) {
                return (string) $set['id'];
            }
        }

        return null;
    }

    private function baseUrl(string $path): string
    {
        return rtrim((string) config('services.fab_cards.base_url'), '/').'/'.ltrim($path, '/');
    }
}
