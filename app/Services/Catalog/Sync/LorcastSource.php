<?php

namespace App\Services\Catalog\Sync;

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Support\Facades\Http;

class LorcastSource implements CatalogSyncSource
{
    /** @var array<string, string> name → lorcast id */
    private array $nameToId = [];

    public function syncSets(Product $product): int
    {
        $sets = Http::get($this->baseUrl('/v0/sets'))->throw()->json('results') ?? [];
        $count = 0;

        foreach ($sets as $set) {
            Set::upsert(
                [['product_id' => $product->id, 'name' => (string) $set['name']]],
                ['product_id', 'name'],
                [],
            );

            $this->nameToId[(string) $set['name']] = (string) $set['id'];
            $count++;
        }

        return $count;
    }

    public function syncCardsForSet(Set $set): int
    {
        $lorcastId = $this->resolveId($set->name);

        if ($lorcastId === null) {
            return 0;
        }

        $cards = Http::get($this->baseUrl("/v0/sets/{$lorcastId}/cards"))->throw()->json('results') ?? [];
        $cardCount = count($cards);

        $cardRows = [];
        $printingMeta = [];

        foreach ($cards as $card) {
            $name = (string) $card['name'];
            $number = (string) $card['collector_number'];

            $cardRows[$name."\0".$number] = [
                'set_id' => $set->id,
                'name' => $name,
                'number' => $number,
                'rarity' => (string) ($card['rarity'] ?? ''),
            ];

            $finishes = $this->extractFinishes($card);

            foreach ($finishes as $finish) {
                $printingMeta[] = [
                    'name' => $name,
                    'number' => $number,
                    'finish' => $finish,
                    'lorcast_id' => (string) ($card['id'] ?? ''),
                    'image_url' => $card['image_uris']['digital']['normal'] ?? null,
                ];
            }
        }

        if ($cardRows !== []) {
            Card::upsert(array_values($cardRows), ['set_id', 'name', 'number'], ['rarity']);
        }

        if ($printingMeta !== []) {
            $this->upsertPrintings($set->id, $printingMeta);
        }

        return $cardCount;
    }

    /** @param  array<string, mixed>  $card */
    private function extractFinishes(array $card): array
    {
        $finishes = [];

        if (! empty($card['foil'])) {
            $finishes[] = 'foil';
        }

        if (! empty($card['non_foil'])) {
            $finishes[] = 'non-foil';
        }

        return $finishes !== [] ? $finishes : ['non-foil'];
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

            $otherIds = $row['lorcast_id'] !== '' ? ['lorcast_id' => $row['lorcast_id']] : null;

            $printings[] = [
                'card_id' => $card->id,
                'finish' => $row['finish'],
                'tcgplayer_id' => null,
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
                ['image_url', 'other_ids'],
            );
        }
    }

    private function resolveId(string $setName): ?string
    {
        if (isset($this->nameToId[$setName])) {
            return $this->nameToId[$setName];
        }

        $sets = Http::get($this->baseUrl('/v0/sets'))->throw()->json('results') ?? [];

        foreach ($sets as $set) {
            if ($set['name'] === $setName) {
                return (string) $set['id'];
            }
        }

        return null;
    }

    private function baseUrl(string $path): string
    {
        return rtrim((string) config('services.lorcast.base_url'), '/').'/'.ltrim($path, '/');
    }
}
