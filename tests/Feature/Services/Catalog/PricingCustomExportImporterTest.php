<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\File;
use App\Models\Product;
use App\Services\Catalog\CatalogUpserter;
use App\Services\Catalog\PricingCustomExportImporter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function fixturePath(): string
{
    return base_path('tests/fixtures/catalog/pricing-custom-export-sample.csv');
}

function importFixture(?string $sourcePath = null): void
{
    $importer = new PricingCustomExportImporter(new CatalogUpserter);
    $importer->import($sourcePath ?? fixturePath());
}

test('first import creates products, sets, and cards with parsed prices', function () {
    importFixture();

    expect(Product::count())->toBe(2);
    expect(Product::where('name', 'Flesh & Blood TCG')->exists())->toBeTrue();
    expect(Product::where('name', 'Magic')->exists())->toBeTrue();

    expect(CardSet::count())->toBe(3);

    expect(Card::count())->toBe(7);

    $boltyn = Card::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($boltyn->product_name)->toBe('Boltyn');
    expect($boltyn->number)->toBe('BOL001');
    expect($boltyn->rarity)->toBe('Majestic');
    expect($boltyn->condition)->toBe('Near Mint');
    expect($boltyn->market_price)->toBe(1250);
    expect($boltyn->low_price)->toBe(1175);

    $emptyLow = Card::where('tcgplayer_id', 5012346)->firstOrFail();
    expect($emptyLow->market_price)->toBe(210);
    expect($emptyLow->low_price)->toBeNull();
});

test('a files row is persisted with the import', function () {
    importFixture();

    $file = File::where('type', 'import')->firstOrFail();
    expect($file->file_path)->toStartWith('imports/pricing/');
    expect($file->original_filename)->toBe('pricing-custom-export-sample.csv');
    Storage::assertExists($file->file_path);
});

test('priced_at is bumped on every product touched', function () {
    importFixture();

    $magic = Product::where('name', 'Magic')->firstOrFail();
    $fab = Product::where('name', 'Flesh & Blood TCG')->firstOrFail();

    expect($magic->priced_at)->not->toBeNull();
    expect($fab->priced_at)->not->toBeNull();
});

test('re-running the same file produces zero logical changes', function () {
    importFixture();
    $cardCountAfterFirst = Card::count();
    $boltyn = Card::where('tcgplayer_id', 4941700)->firstOrFail();
    $marketBefore = $boltyn->market_price;
    $lowBefore = $boltyn->low_price;
    $productBefore = $boltyn->product_name;

    importFixture();

    expect(Card::count())->toBe($cardCountAfterFirst);
    expect(Product::count())->toBe(2);
    expect(CardSet::count())->toBe(3);

    $boltyn->refresh();
    expect($boltyn->market_price)->toBe($marketBefore);
    expect($boltyn->low_price)->toBe($lowBefore);
    expect($boltyn->product_name)->toBe($productBefore);
});

test('second import refreshes market and low price but not identity', function () {
    importFixture();

    $modified = sys_get_temp_dir().'/pricing-custom-export-modified.csv';
    file_put_contents($modified, str_replace(
        '"4941700","Flesh & Blood TCG","Blitz Deck: Monarch - Boltyn","Boltyn","","BOL001","Majestic","Near Mint","12.50","","13.5000","11.7500"',
        '"4941700","Flesh & Blood TCG","Blitz Deck: Monarch - Boltyn","RENAMED Boltyn","","RENAMED-001","Common","Lightly Played","99.99","","100.0000","98.0000"',
        file_get_contents(fixturePath())
    ));

    importFixture($modified);

    $boltyn = Card::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($boltyn->market_price)->toBe(9999);
    expect($boltyn->low_price)->toBe(9800);
    expect($boltyn->product_name)->toBe('Boltyn');
    expect($boltyn->number)->toBe('BOL001');
    expect($boltyn->rarity)->toBe('Majestic');
    expect($boltyn->condition)->toBe('Near Mint');

    @unlink($modified);
});

test('artisan command imports a CSV', function () {
    $this->artisan('catalog:import-pricing-custom-export', ['path' => fixturePath()])
        ->expectsOutputToContain('Imported 7 rows, touched 2 product(s).')
        ->assertSuccessful();
});

test('artisan command fails on missing file', function () {
    $this->artisan('catalog:import-pricing-custom-export', ['path' => '/no/such/file.csv'])
        ->expectsOutputToContain('Cannot read CSV')
        ->assertFailed();
});
