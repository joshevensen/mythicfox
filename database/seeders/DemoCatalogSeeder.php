<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Database\Seeder;

/**
 * Demo dataset for screenshotting and manual UI exploration in later phases.
 * NOT registered in DatabaseSeeder. Run manually via:
 *   php artisan db:seed --class=DemoCatalogSeeder
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        $magic = Product::where('name', 'Magic')->firstOrFail();
        $lorcana = Product::where('name', 'Lorcana TCG')->firstOrFail();
        $fab = Product::where('name', 'Flesh & Blood TCG')->firstOrFail();

        $tcgplayerSeed = 9_000_000;

        $this->seedProduct($magic, [
            ['name' => 'Wilds of Eldraine', 'numbers' => fn ($i) => sprintf('%03d/271', $i), 'rarities' => ['C', 'U', 'R', 'M']],
            ['name' => 'Lord of the Rings', 'numbers' => fn ($i) => sprintf('%03d/281', $i), 'rarities' => ['C', 'U', 'R', 'M']],
        ], $tcgplayerSeed);

        $this->seedProduct($lorcana, [
            ['name' => 'The First Chapter', 'numbers' => fn ($i) => (string) (100 + $i), 'rarities' => ['Common', 'Uncommon', 'Rare', 'Super Rare', 'Legendary']],
            ['name' => 'Rise of the Floodborn', 'numbers' => fn ($i) => (string) (200 + $i), 'rarities' => ['Common', 'Uncommon', 'Rare', 'Enchanted']],
        ], $tcgplayerSeed + 1_000);

        $this->seedProduct($fab, [
            ['name' => 'Welcome to Rathe, Unlimited', 'numbers' => fn ($i) => sprintf('WTR%03d', $i), 'rarities' => ['Common', 'Rare', 'Super Rare', 'Majestic']],
            ['name' => 'Monarch - Boltyn', 'numbers' => fn ($i) => sprintf('BOL%03d', $i), 'rarities' => ['Common', 'Rare', 'Majestic']],
        ], $tcgplayerSeed + 2_000);
    }

    /**
     * @param  array<int, array{name:string, numbers:callable, rarities:list<string>}>  $sets
     */
    private function seedProduct(Product $product, array $sets, int $tcgplayerSeed): void
    {
        $offset = 0;
        $perProductInventory = 0;

        foreach ($sets as $setSpec) {
            $set = Set::factory()->create([
                'product_id' => $product->id,
                'name' => $setSpec['name'],
            ]);

            for ($i = 1; $i <= 20; $i++) {
                $card = Card::factory()->create([
                    'set_id' => $set->id,
                    'tcgplayer_id' => $tcgplayerSeed + $offset++,
                    'product_name' => "Demo Card {$set->name} {$i}",
                    'number' => ($setSpec['numbers'])($i),
                    'rarity' => $setSpec['rarities'][$i % count($setSpec['rarities'])],
                    'condition' => $i % 5 === 0 ? 'Near Mint Foil' : 'Near Mint',
                    'market_price' => $i % 7 === 0 ? null : ($i * 50),
                    'low_price' => $i % 7 === 0 ? null : ($i * 45),
                ]);

                if ($perProductInventory < 10) {
                    Inventory::factory()->create([
                        'card_id' => $card->id,
                        'quantity' => $perProductInventory === 0 ? 0 : fake()->numberBetween(1, 8),
                        'override_price' => $perProductInventory === 1 ? 999 : null,
                        'calculated_price' => $perProductInventory === 2 ? null : ($card->market_price ?? null),
                    ]);
                    $perProductInventory++;
                }
            }
        }
    }
}
