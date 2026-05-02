<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\Catalog\PricingExporter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function readExportedRows(string $path): array
{
    $contents = Storage::get($path);
    $lines = preg_split('/\r?\n/', trim($contents));

    return array_map(fn ($l) => str_getcsv($l, escape: '\\'), $lines);
}

function seedExportFixture(): array
{
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = CardSet::factory()->create(['product_id' => $product->id, 'name' => 'Wilds of Eldraine']);

    $cardA = Card::factory()->create([
        'set_id' => $set->id,
        'tcgplayer_id' => 5012345,
        'product_name' => 'Edgewall Innkeeper',
        'number' => '97/204',
        'rarity' => 'R',
        'condition' => 'Near Mint',
        'market_price' => 250,
        'low_price' => 225,
    ]);
    $cardB = Card::factory()->create([
        'set_id' => $set->id,
        'tcgplayer_id' => 5012346,
        'product_name' => 'Sauron, the Dark Lord',
        'number' => '100',
        'rarity' => 'M',
        'condition' => 'Near Mint Foil',
        'market_price' => null,
        'low_price' => null,
    ]);
    $cardZero = Card::factory()->create([
        'set_id' => $set->id,
        'tcgplayer_id' => 5012347,
        'product_name' => 'Bulk Common',
        'number' => '1',
        'rarity' => 'C',
        'condition' => 'Damaged',
        'market_price' => 10,
        'low_price' => 5,
    ]);

    return [
        'override' => Inventory::factory()->create([
            'card_id' => $cardA->id,
            'quantity' => 4,
            'calculated_price' => 240,
            'override_price' => 199,
        ]),
        'nullPriced' => Inventory::factory()->create([
            'card_id' => $cardB->id,
            'quantity' => 1,
            'calculated_price' => null,
            'override_price' => null,
        ]),
        'zeroQty' => Inventory::factory()->create([
            'card_id' => $cardZero->id,
            'quantity' => 0,
            'calculated_price' => 25,
            'override_price' => null,
        ]),
    ];
}

test('header matches the 16-column TCGPlayer order verbatim', function () {
    seedExportFixture();

    $result = (new PricingExporter)->export();
    $rows = readExportedRows($result->file->file_path);

    expect($rows[0])->toBe(PricingExporter::Header);
});

test('one row is emitted per inventory entry, including zero-quantity rows', function () {
    seedExportFixture();

    $result = (new PricingExporter)->export();

    expect($result->rowsWritten)->toBe(3);

    $rows = readExportedRows($result->file->file_path);
    expect(count($rows))->toBe(4); // header + 3
});

test('TCG Marketplace Price uses override when present and calculated otherwise', function () {
    seedExportFixture();

    $result = (new PricingExporter)->export();
    $rows = readExportedRows($result->file->file_path);

    $byId = [];
    foreach (array_slice($rows, 1) as $row) {
        $byId[(int) $row[0]] = $row;
    }

    // override row: override_price = 199 → "1.99"
    expect($byId[5012345][14])->toBe('1.99');
    // zero-qty row: no override, calculated 25 → "0.25"
    expect($byId[5012347][14])->toBe('0.25');
    // null-priced row: empty
    expect($byId[5012346][14])->toBe('');
});

test('decimal formatting round-trips cents to two-decimal strings', function () {
    seedExportFixture();

    $result = (new PricingExporter)->export();
    $rows = readExportedRows($result->file->file_path);
    $byId = [];
    foreach (array_slice($rows, 1) as $row) {
        $byId[(int) $row[0]] = $row;
    }

    expect($byId[5012345][8])->toBe('2.50'); // market 250 cents
    expect($byId[5012345][11])->toBe('2.25'); // low 225 cents
    expect($byId[5012346][8])->toBe(''); // null market
    expect($byId[5012346][11])->toBe(''); // null low
});

test('Add to Quantity is 0 on every row', function () {
    seedExportFixture();

    $result = (new PricingExporter)->export();
    $rows = readExportedRows($result->file->file_path);

    foreach (array_slice($rows, 1) as $row) {
        expect($row[13])->toBe('0');
    }
});

test('Title, TCG Direct Low, TCG Low Price With Shipping, and Photo URL are emitted empty', function () {
    seedExportFixture();

    $result = (new PricingExporter)->export();
    $rows = readExportedRows($result->file->file_path);

    foreach (array_slice($rows, 1) as $row) {
        expect($row[4])->toBe(''); // Title
        expect($row[9])->toBe(''); // TCG Direct Low
        expect($row[10])->toBe(''); // TCG Low Price With Shipping
        expect($row[15])->toBe(''); // Photo URL
    }
});

test('last_exported_price is updated to effective price on success', function () {
    $entries = seedExportFixture();

    (new PricingExporter)->export();

    expect($entries['override']->refresh()->last_exported_price)->toBe(199);
    expect($entries['zeroQty']->refresh()->last_exported_price)->toBe(25);
    expect($entries['nullPriced']->refresh()->last_exported_price)->toBeNull();
});

test('files row is persisted at exports/pricing/YYYY/MM/...', function () {
    seedExportFixture();

    $result = (new PricingExporter)->export();

    expect($result->file->type)->toBe('export');
    expect($result->file->file_path)->toStartWith('exports/pricing/');
    Storage::assertExists($result->file->file_path);
});

test('a write failure does not update last_exported_price', function () {
    $entries = seedExportFixture();
    $beforeOverride = $entries['override']->last_exported_price;
    $beforeZero = $entries['zeroQty']->last_exported_price;

    Storage::shouldReceive('put')->andReturn(false);

    expect(fn () => (new PricingExporter)->export())->toThrow(RuntimeException::class);

    expect($entries['override']->refresh()->last_exported_price)->toBe($beforeOverride);
    expect($entries['zeroQty']->refresh()->last_exported_price)->toBe($beforeZero);
});

test('artisan command runs full recompute → export flow', function () {
    seedExportFixture();

    $this->artisan('catalog:export-pricing')
        ->expectsOutputToContain('Recompute:')
        ->expectsOutputToContain('Export: 3 rows written.')
        ->assertSuccessful();
});
