<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\File;
use App\Models\Product;
use App\Models\Set;
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

    expect(Set::count())->toBe(3);

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
    expect(Set::count())->toBe(3);

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

test('rows with empty Number and condition Unopened/Opened are routed to decks (case-insensitive)', function () {
    $tmp = sys_get_temp_dir().'/pricing-with-decks.csv';
    file_put_contents($tmp, <<<'CSV'
TCGplayer Id,Product Line,Set Name,Product Name,Title,Number,Rarity,Condition,TCG Market Price,TCG Direct Low,TCG Low Price With Shipping,TCG Low Price,Total Quantity,Add to Quantity,TCG Marketplace Price,Photo URL
"7000001","Flesh & Blood TCG","Blitz Deck: Monarch - Boltyn","Monarch Boltyn Deck","","","Deck","Unopened","20.00","","","19.00","0","0","20.00",""
"7000002","Flesh & Blood TCG","Blitz Deck: Monarch - Boltyn","Monarch Boltyn Deck","","","Deck","opened","15.00","","","14.00","0","0","15.00",""
"7000003","Flesh & Blood TCG","Blitz Deck: Monarch - Boltyn","Real Card","","BOL050","Common","Near Mint","0.50","","","0.45","0","0","0.50",""
CSV);

    importFixture($tmp);

    expect(Deck::count())->toBe(2);
    expect(Card::where('tcgplayer_id', 7000003)->exists())->toBeTrue();
    expect(Card::where('tcgplayer_id', 7000001)->exists())->toBeFalse();

    $unopened = Deck::where('tcgplayer_id', 7000001)->firstOrFail();
    expect($unopened->product_name)->toBe('Monarch Boltyn Deck');
    expect($unopened->condition)->toBe('Unopened');
    expect($unopened->market_price)->toBe(2000);
    expect($unopened->low_price)->toBe(1900);

    // Case-insensitive match: lowercase 'opened' still routes to decks.
    $opened = Deck::where('tcgplayer_id', 7000002)->firstOrFail();
    expect($opened->condition)->toBe('opened');

    @unlink($tmp);
});

test('a row with empty Number but a non-deck condition stays in cards (rule requires both)', function () {
    $tmp = sys_get_temp_dir().'/pricing-empty-number-non-deck.csv';
    file_put_contents($tmp, <<<'CSV'
TCGplayer Id,Product Line,Set Name,Product Name,Title,Number,Rarity,Condition,TCG Market Price,TCG Direct Low,TCG Low Price With Shipping,TCG Low Price,Total Quantity,Add to Quantity,TCG Marketplace Price,Photo URL
"8000001","Magic","Wilds of Eldraine","Mystery No-Number","","","R","Near Mint","1.00","","","0.90","0","0","1.00",""
CSV);

    importFixture($tmp);

    expect(Deck::count())->toBe(0);
    expect(Card::where('tcgplayer_id', 8000001)->exists())->toBeTrue();

    @unlink($tmp);
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
