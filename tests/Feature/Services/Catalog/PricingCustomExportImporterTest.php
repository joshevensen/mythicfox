<?php

use App\Models\Card;
use App\Models\File;
use App\Models\Printing;
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

test('first import creates products, sets, canonical cards, and printings with parsed prices', function () {
    importFixture();

    expect(Product::count())->toBe(2);
    expect(Product::where('name', 'Flesh & Blood TCG')->exists())->toBeTrue();
    expect(Product::where('name', 'Magic')->exists())->toBeTrue();

    expect(Set::count())->toBe(3);
    expect(Card::count())->toBe(5);
    expect(Printing::count())->toBe(6);

    $boltyn = Card::where('name', 'Boltyn')->where('number', 'BOL001')->firstOrFail();
    expect($boltyn->rarity)->toBe('Majestic');

    $nonFoil = Printing::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($nonFoil->card_id)->toBe($boltyn->id);
    expect($nonFoil->finish)->toBe('non-foil');
    expect($nonFoil->market_price)->toBe(1250);
    expect($nonFoil->low_price)->toBe(1175);

    $foil = Printing::where('tcgplayer_id', 4941701)->firstOrFail();
    expect($foil->card_id)->toBe($boltyn->id);
    expect($foil->finish)->toBe('foil');

    $emptyLow = Printing::where('tcgplayer_id', 5012346)->firstOrFail();
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
    $printingCountAfterFirst = Printing::count();
    $boltyn = Card::where('name', 'Boltyn')->where('number', 'BOL001')->firstOrFail();
    $nonFoil = Printing::where('tcgplayer_id', 4941700)->firstOrFail();
    $marketBefore = $nonFoil->market_price;

    importFixture();

    expect(Card::count())->toBe($cardCountAfterFirst);
    expect(Printing::count())->toBe($printingCountAfterFirst);
    expect(Product::count())->toBe(2);
    expect(Set::count())->toBe(3);

    $boltyn->refresh();
    $nonFoil->refresh();
    expect($boltyn->name)->toBe('Boltyn');
    expect($nonFoil->market_price)->toBe($marketBefore);
});

test('second import creates a new canonical identity when name or number changes', function () {
    importFixture();

    $modified = sys_get_temp_dir().'/pricing-custom-export-modified.csv';
    file_put_contents($modified, str_replace(
        '"4941700","Flesh & Blood TCG","Monarch - Boltyn","Boltyn","","BOL001","Majestic","Near Mint","12.50","","13.5000","11.7500"',
        '"4941700","Flesh & Blood TCG","Monarch - Boltyn","RENAMED Boltyn","","RENAMED-001","Common","Lightly Played","99.99","","100.0000","98.0000"',
        file_get_contents(fixturePath())
    ));

    importFixture($modified);

    $renamed = Card::where('name', 'RENAMED Boltyn')->where('number', 'RENAMED-001')->firstOrFail();
    $printing = Printing::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($printing->card_id)->toBe($renamed->id);
    expect($printing->market_price)->toBe(9999);
    expect($printing->low_price)->toBe(9800);

    @unlink($modified);
});

test('rows with empty Number are skipped', function () {
    $tmp = sys_get_temp_dir().'/pricing-with-sealed-products.csv';
    file_put_contents($tmp, <<<'CSV'
TCGplayer Id,Product Line,Set Name,Product Name,Title,Number,Rarity,Condition,TCG Market Price,TCG Direct Low,TCG Low Price With Shipping,TCG Low Price,Total Quantity,Add to Quantity,TCG Marketplace Price,Photo URL
"7000001","Flesh & Blood TCG","Monarch - Boltyn","Monarch Boltyn Sealed","","","Sealed","Unopened","20.00","","","19.00","0","0","20.00",""
"7000002","Flesh & Blood TCG","Monarch - Boltyn","Monarch Boltyn Sealed","","","Sealed","opened","15.00","","","14.00","0","0","15.00",""
"7000003","Flesh & Blood TCG","Monarch - Boltyn","Real Card","","BOL050","Common","Near Mint","0.50","","","0.45","0","0","0.50",""
CSV);

    importFixture($tmp);

    expect(Printing::where('tcgplayer_id', 7000003)->exists())->toBeTrue();
    expect(Printing::where('tcgplayer_id', 7000001)->exists())->toBeFalse();
    expect(Printing::where('tcgplayer_id', 7000002)->exists())->toBeFalse();

    @unlink($tmp);
});

test('finish is derived from condition text', function () {
    $tmp = sys_get_temp_dir().'/pricing-finishes.csv';
    file_put_contents($tmp, <<<'CSV'
TCGplayer Id,Product Line,Set Name,Product Name,Title,Number,Rarity,Condition,TCG Market Price,TCG Direct Low,TCG Low Price With Shipping,TCG Low Price,Total Quantity,Add to Quantity,TCG Marketplace Price,Photo URL
"8000001","Magic","Wilds of Eldraine","Mystery","","1","R","Near Mint","1.00","","","0.90","0","0","1.00",""
"8000002","Magic","Wilds of Eldraine","Mystery","","1","R","Near Mint Foil","2.00","","","1.90","0","0","2.00",""
"8000003","Magic","Wilds of Eldraine","Mystery","","1","R","Near Mint Etched Foil","3.00","","","2.90","0","0","3.00",""
CSV);

    importFixture($tmp);

    expect(Printing::orderBy('finish')->pluck('finish')->all())
        ->toBe(['etched', 'foil', 'non-foil']);

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
