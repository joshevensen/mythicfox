<?php

namespace App\Services\Catalog;

use App\Models\Card;
use App\Models\Deck;
use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\Support\CentsParser;
use Illuminate\Support\Carbon;

/**
 * Upserts catalog rows from a single PricingCustomExport / MyPricing CSV row.
 *
 * Shared by PricingCustomExportImporter (10-005) and MyPricingImporter (10-006).
 * Identity protection: on update, never overwrites product_name / number / rarity / condition.
 * Pricing rule fields on products and sets are never touched on update.
 */
class CatalogUpserter
{
    /**
     * Sealed-product condition strings that mark a CSV row as a deck rather
     * than a singleton card. Matched case-insensitively.
     */
    private const DECK_CONDITIONS = ['unopened', 'opened'];

    /** @var array<string, int> name → product id */
    private array $productCache = [];

    /** @var array<string, int> "{product_id}\0{name}" → set id */
    private array $setCache = [];

    /** @var array<int, true> product ids touched in this run */
    private array $touchedProductIds = [];

    /** @var list<array<string, mixed>> buffered card upsert rows */
    private array $cardBuffer = [];

    /** @var list<array<string, mixed>> buffered deck upsert rows */
    private array $deckBuffer = [];

    public function __construct(private readonly int $cardChunkSize = 500) {}

    /**
     * @param  array<string, string|null>  $row  raw CSV row keyed by header
     */
    public function ingest(array $row): void
    {
        $productLine = trim((string) ($row['Product Line'] ?? ''));
        $setName = trim((string) ($row['Set Name'] ?? ''));
        $tcgplayerId = (int) ($row['TCGplayer Id'] ?? 0);

        if ($productLine === '' || $setName === '' || $tcgplayerId === 0) {
            return;
        }

        $productId = $this->resolveProduct($productLine);
        $setId = $this->resolveSet($productId, $setName);

        $this->touchedProductIds[$productId] = true;

        $number = (string) ($row['Number'] ?? '');
        $condition = (string) ($row['Condition'] ?? '');

        if ($this->isDeckRow($number, $condition)) {
            $this->deckBuffer[] = [
                'set_id' => $setId,
                'tcgplayer_id' => $tcgplayerId,
                'product_name' => (string) ($row['Product Name'] ?? ''),
                'rarity' => (string) ($row['Rarity'] ?? ''),
                'condition' => $condition,
                'market_price' => CentsParser::parse($row['TCG Market Price'] ?? null),
                'low_price' => CentsParser::parse($row['TCG Low Price'] ?? null),
            ];

            if (count($this->deckBuffer) >= $this->cardChunkSize) {
                $this->flushDeckBuffer();
            }

            return;
        }

        $this->cardBuffer[] = [
            'set_id' => $setId,
            'tcgplayer_id' => $tcgplayerId,
            'product_name' => (string) ($row['Product Name'] ?? ''),
            'number' => $number,
            'rarity' => (string) ($row['Rarity'] ?? ''),
            'condition' => $condition,
            'market_price' => CentsParser::parse($row['TCG Market Price'] ?? null),
            'low_price' => CentsParser::parse($row['TCG Low Price'] ?? null),
        ];

        if (count($this->cardBuffer) >= $this->cardChunkSize) {
            $this->flushCardBuffer();
        }
    }

    public function flush(): void
    {
        $this->flushCardBuffer();
        $this->flushDeckBuffer();
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

    private function flushCardBuffer(): void
    {
        if ($this->cardBuffer === []) {
            return;
        }

        // Identity columns (product_name, number, rarity, condition) are inserted but never
        // overwritten on conflict; only market_price and low_price refresh.
        Card::upsert(
            $this->cardBuffer,
            ['tcgplayer_id'],
            ['market_price', 'low_price'],
        );

        $this->cardBuffer = [];
    }

    private function flushDeckBuffer(): void
    {
        if ($this->deckBuffer === []) {
            return;
        }

        Deck::upsert(
            $this->deckBuffer,
            ['tcgplayer_id'],
            ['market_price', 'low_price'],
        );

        $this->deckBuffer = [];
    }

    private function isDeckRow(string $number, string $condition): bool
    {
        if (trim($number) !== '') {
            return false;
        }

        return in_array(strtolower(trim($condition)), self::DECK_CONDITIONS, true);
    }
}
