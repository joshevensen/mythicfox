<?php

namespace App\Services\Catalog;

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\Support\CentsParser;
use Illuminate\Support\Carbon;

/**
 * Upserts catalog rows from a single PricingCustomExport CSV row.
 *
 * Shared by PricingCustomExportImporter and catalog-style pricing imports.
 * Cards are canonical identities; per-finish provider IDs and prices live on
 * printings.
 * Pricing rule fields on products and sets are never touched on update.
 */
class CatalogUpserter
{
    /** @var array<string, int> name → product id */
    private array $productCache = [];

    /** @var array<string, int> "{product_id}\0{name}" → set id */
    private array $setCache = [];

    /** @var array<int, true> product ids touched in this run */
    private array $touchedProductIds = [];

    /** @var array<string, array<string, mixed>> buffered card upsert rows keyed by canonical identity */
    private array $cardBuffer = [];

    /** @var array<string, array<string, mixed>> buffered printing upsert rows keyed by card identity + finish */
    private array $printingBuffer = [];

    public function __construct(private readonly int $chunkSize = 500) {}

    /**
     * @param  array<string, string|null>  $row  raw CSV row keyed by header
     */
    public function ingest(array $row): void
    {
        $productLine = trim((string) ($row['Product Line'] ?? ''));
        $setName = trim((string) ($row['Set Name'] ?? ''));
        $productName = trim((string) ($row['Product Name'] ?? ''));
        $number = trim((string) ($row['Number'] ?? ''));
        $tcgplayerId = (int) ($row['TCGplayer Id'] ?? 0);

        if ($productLine === '' || $setName === '' || $productName === '' || $number === '' || $tcgplayerId === 0) {
            return;
        }

        $productId = $this->resolveProduct($productLine);
        $setId = $this->resolveSet($productId, $setName);

        $this->touchedProductIds[$productId] = true;

        $condition = (string) ($row['Condition'] ?? '');
        $identityKey = $this->identityKey($setId, $productName, $number);

        $this->cardBuffer[$identityKey] = [
            'set_id' => $setId,
            'name' => $productName,
            'number' => $number,
            'rarity' => (string) ($row['Rarity'] ?? ''),
        ];

        $finish = $this->finishFromCondition($condition);
        $this->printingBuffer[$identityKey."\0".$finish] = [
            'identity_key' => $identityKey,
            'finish' => $finish,
            'tcgplayer_id' => $tcgplayerId,
            'justtcg_id' => null,
            'other_ids' => null,
            'image_url' => $this->nullableString($row['Photo URL'] ?? null),
            'market_price' => CentsParser::parse($row['TCG Market Price'] ?? null),
            'low_price' => CentsParser::parse($row['TCG Low Price'] ?? null),
        ];

        if (count($this->printingBuffer) >= $this->chunkSize) {
            $this->flushBuffers();
        }
    }

    public function flush(): void
    {
        $this->flushBuffers();
    }

    public function bumpPricedAt(): void
    {
        if ($this->touchedProductIds === []) {
            return;
        }

        Product::whereIn('id', array_keys($this->touchedProductIds))
            ->update(['priced_at' => Carbon::now()]);
    }

    /**
     * @return list<int>
     */
    public function touchedProductIds(): array
    {
        return array_keys($this->touchedProductIds);
    }

    private function resolveProduct(string $name): int
    {
        if (isset($this->productCache[$name])) {
            return $this->productCache[$name];
        }

        $product = Product::firstOrCreate(['name' => $name]);

        return $this->productCache[$name] = $product->id;
    }

    private function resolveSet(int $productId, string $name): int
    {
        $key = $productId."\0".$name;
        if (isset($this->setCache[$key])) {
            return $this->setCache[$key];
        }

        $set = Set::firstOrCreate(['product_id' => $productId, 'name' => $name]);

        return $this->setCache[$key] = $set->id;
    }

    private function flushBuffers(): void
    {
        if ($this->cardBuffer === []) {
            return;
        }

        Card::upsert(
            array_values($this->cardBuffer),
            ['set_id', 'name', 'number'],
            ['rarity'],
        );

        $cardIds = $this->loadCardIds($this->cardBuffer);
        $printings = [];

        foreach ($this->printingBuffer as $printing) {
            $cardId = $cardIds[$printing['identity_key']] ?? null;

            if ($cardId === null) {
                continue;
            }

            unset($printing['identity_key']);
            $printings[] = ['card_id' => $cardId, ...$printing];
        }

        if ($printings !== []) {
            foreach ($printings as $printing) {
                if ($printing['tcgplayer_id'] === null) {
                    continue;
                }

                Printing::query()
                    ->where('tcgplayer_id', $printing['tcgplayer_id'])
                    ->where(function ($query) use ($printing) {
                        $query->where('card_id', '!=', $printing['card_id'])
                            ->orWhere('finish', '!=', $printing['finish']);
                    })
                    ->delete();
            }

            Printing::upsert(
                $printings,
                ['card_id', 'finish'],
                ['tcgplayer_id', 'justtcg_id', 'other_ids', 'image_url', 'market_price', 'low_price'],
            );
        }

        $this->cardBuffer = [];
        $this->printingBuffer = [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $cards
     * @return array<string, int>
     */
    private function loadCardIds(array $cards): array
    {
        $setIds = array_unique(array_column($cards, 'set_id'));
        $names = array_unique(array_column($cards, 'name'));
        $numbers = array_unique(array_column($cards, 'number'));

        $rows = Card::query()
            ->whereIn('set_id', $setIds)
            ->whereIn('name', $names)
            ->whereIn('number', $numbers)
            ->get(['id', 'set_id', 'name', 'number']);

        return $rows->mapWithKeys(fn (Card $card) => [
            $this->identityKey((int) $card->set_id, (string) $card->name, (string) $card->number) => (int) $card->id,
        ])->all();
    }

    private function identityKey(int $setId, string $name, string $number): string
    {
        return $setId."\0".$name."\0".$number;
    }

    private function finishFromCondition(string $condition): string
    {
        $normalized = strtolower($condition);

        if (str_contains($normalized, 'etched')) {
            return 'etched';
        }

        if (str_contains($normalized, 'foil')) {
            return 'foil';
        }

        return 'non-foil';
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
