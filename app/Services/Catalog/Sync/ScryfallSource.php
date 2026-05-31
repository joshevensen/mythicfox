<?php

namespace App\Services\Catalog\Sync;

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Support\Facades\Http;

class ScryfallSource implements CatalogSyncSource
{
    private const INCLUDED_SET_TYPES = [
        'expansion', 'core', 'masters', 'draft_innovation',
        'commander', 'arsenal', 'from_the_vault', 'spellbook',
        'premium_deck', 'duel_deck', 'starter',
    ];

    private const FINISH_MAP = [
        'nonfoil' => 'non-foil',
        'foil' => 'foil',
        'etched' => 'etched',
        'glossy' => 'glossy',
    ];

    /** @var array<string, string> name → code, populated on first fetchSets() call */
    private array $nameToCode = [];

    /** @var list<array<string, mixed>>|null */
    private ?array $cachedSets = null;

    public function syncSets(Product $product): int
    {
        $scryfallSets = $this->fetchSets();
        $count = 0;

        foreach ($scryfallSets as $set) {
            if (! in_array($set['set_type'], self::INCLUDED_SET_TYPES, true)) {
                continue;
            }

            Set::upsert(
                [['product_id' => $product->id, 'name' => $set['name']]],
                ['product_id', 'name'],
                [],
            );

            $this->nameToCode[$set['name']] = $set['code'];
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

        $url = $this->baseUrl('/cards/search');
        $params = ['order' => 'set', 'q' => "e:{$code}", 'unique' => 'prints'];
        $cardCount = 0;

        do {
            usleep(100_000);

            $response = Http::get($url, $params)->throw()->json();
            $cards = $response['data'] ?? [];

            $this->upsertCards($set, $cards);
            $cardCount += count($cards);

            $hasMore = (bool) ($response['has_more'] ?? false);
            $url = (string) ($response['next_page'] ?? $url);
            $params = [];
        } while ($hasMore);

        return $cardCount;
    }

    /** @param list<array<string, mixed>> $cards */
    private function upsertCards(Set $set, array $cards): void
    {
        $cardRows = [];
        $printingMeta = [];

        foreach ($cards as $card) {
            $name = (string) $card['name'];
            $number = (string) $card['collector_number'];
            $finishes = (array) ($card['finishes'] ?? ['nonfoil']);

            $cardRows[$name."\0".$number] = [
                'set_id' => $set->id,
                'name' => $name,
                'number' => $number,
                'rarity' => (string) $card['rarity'],
            ];

            $imageUrl = $card['image_uris']['normal']
                ?? $card['card_faces'][0]['image_uris']['normal']
                ?? null;

            $tcgplayerId = isset($card['tcgplayer_id']) && count($finishes) === 1
                ? (int) $card['tcgplayer_id']
                : null;

            foreach ($finishes as $rawFinish) {
                $printingMeta[] = [
                    'name' => $name,
                    'number' => $number,
                    'finish' => self::FINISH_MAP[$rawFinish] ?? $rawFinish,
                    'tcgplayer_id' => $tcgplayerId,
                    'image_url' => $imageUrl,
                ];
            }
        }

        if ($cardRows !== []) {
            Card::upsert(array_values($cardRows), ['set_id', 'name', 'number'], ['rarity']);
        }

        if ($printingMeta !== []) {
            $this->upsertPrintings($set->id, $printingMeta);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
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

            $printings[] = [
                'card_id' => $card->id,
                'finish' => $row['finish'],
                'tcgplayer_id' => $row['tcgplayer_id'],
                'image_url' => $row['image_url'],
                'justtcg_id' => null,
                'other_ids' => null,
                'market_price' => null,
                'low_price' => null,
            ];
        }

        if ($printings !== []) {
            Printing::upsert(
                $printings,
                ['card_id', 'finish'],
                ['tcgplayer_id', 'image_url'],
            );
        }
    }

    private function resolveCode(string $setName): ?string
    {
        if (isset($this->nameToCode[$setName])) {
            return $this->nameToCode[$setName];
        }

        foreach ($this->fetchSets() as $set) {
            if ($set['name'] === $setName) {
                return (string) $set['code'];
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function fetchSets(): array
    {
        if ($this->cachedSets !== null) {
            return $this->cachedSets;
        }

        $this->cachedSets = Http::get($this->baseUrl('/sets'))->throw()->json('data') ?? [];

        return $this->cachedSets;
    }

    private function baseUrl(string $path): string
    {
        return rtrim((string) config('services.scryfall.base_url'), '/').'/'.ltrim($path, '/');
    }
}
