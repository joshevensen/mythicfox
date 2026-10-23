<?php

use App\Catalog\Queries\RarityOptionsQuery;
use App\Models\Card;
use App\Models\File;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\CatalogUpserter;
use App\Services\Catalog\PricingCustomExportImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function fixturePath(): string
{
    return base_path('tests/fixtures/catalog/pricing-custom-export-sample.csv');
}

function importFixture(?string $sourcePath = null, int $chunkSize = 500): void
{
    $importer = new PricingCustomExportImporter(new CatalogUpserter($chunkSize));
    $importer->import($sourcePath ?? fixturePath());
}

/**
 * @param  list<string>  $rows  CSV data rows (without header)
 */
function writePricingCsv(string $name, array $rows): string
{
    $path = sys_get_temp_dir().'/'.$name;
    $header = 'TCGplayer Id,Product Line,Set Name,Product Name,Title,Number,Rarity,Condition,TCG Market Price,TCG Direct Low,TCG Low Price With Shipping,TCG Low Price,Total Quantity,Add to Quantity,TCG Marketplace Price,Photo URL';
    file_put_contents($path, implode("\n", [$header, ...$rows])."\n");

    return $path;
}

/**
 * @return array{nm: array{0: int|null, 1: int|null}, lp: array{0: int|null, 1: int|null}, mp: array{0: int|null, 1: int|null}}
 */
function conditionPrices(Printing $printing): array
{
    return [
        'nm' => [$printing->nm_market_price, $printing->nm_low_price],
        'lp' => [$printing->lp_market_price, $printing->lp_low_price],
        'mp' => [$printing->mp_market_price, $printing->mp_low_price],
    ];
}

function printingFor(string $name, string $number, string $finish): Printing
{
    return Printing::query()
        ->where('finish', $finish)
        ->whereHas('card', fn ($query) => $query->where('name', $name)->where('number', $number))
        ->firstOrFail();
}

/**
 * @return list<string>
 */
function threeConditionRows(): array
{
    return [
        '"9100001","Magic","Test Set","Order Card","","1","R","Near Mint","5.00","","","4.50","0","0","",""',
        '"9100002","Magic","Test Set","Order Card","","1","R","Lightly Played","4.00","","","3.50","0","0","",""',
        '"9100003","Magic","Test Set","Order Card","","1","R","Moderately Played","3.00","","","2.50","0","0","",""',
        '"9100004","Magic","Test Set","Order Card","","1","R","Near Mint Foil","9.00","","","8.50","0","0","",""',
        '"9100005","Magic","Test Set","Order Card","","1","R","Lightly Played Foil","8.00","","","7.50","0","0","",""',
        '"9100006","Magic","Test Set","Order Card","","1","R","Moderately Played Foil","7.00","","","6.50","0","0","",""',
    ];
}

test('first import creates products, sets, canonical cards, and printings with parsed prices', function () {
    importFixture();

    expect(Product::count())->toBe(3);
    expect(Product::where('name', 'Flesh & Blood TCG')->exists())->toBeTrue();
    expect(Product::where('name', 'Magic')->exists())->toBeTrue();

    expect(Set::count())->toBe(8);
    expect(Card::count())->toBe(16);
    expect(Printing::count())->toBe(18);

    $boltyn = Card::where('name', 'Boltyn')->where('number', 'BOL001')->firstOrFail();
    expect($boltyn->rarity)->toBe('Majestic');

    $nonFoil = Printing::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($nonFoil->card_id)->toBe($boltyn->id);
    expect($nonFoil->finish)->toBe('non-foil');
    expect(conditionPrices($nonFoil))->toBe(['nm' => [1250, 1175], 'lp' => [null, null], 'mp' => [null, null]]);

    $foil = Printing::where('tcgplayer_id', 4941701)->firstOrFail();
    expect($foil->card_id)->toBe($boltyn->id);
    expect($foil->finish)->toBe('foil');

    $edgewall = printingFor('Edgewall Innkeeper', '97/204', 'non-foil');
    expect($edgewall->tcgplayer_id)->toBe(5012345);
    expect(conditionPrices($edgewall))->toBe(['nm' => [250, 225], 'lp' => [210, null], 'mp' => [null, null]]);

    $lossesNonFoil = printingFor('Acceptable Losses', '179/298', 'non-foil');
    expect($lossesNonFoil->tcgplayer_id)->toBe(8926937);
    expect(conditionPrices($lossesNonFoil))->toBe(['nm' => [54, 40], 'lp' => [53, 40], 'mp' => [16, 24]]);

    $lossesFoil = printingFor('Acceptable Losses', '179/298', 'foil');
    expect($lossesFoil->tcgplayer_id)->toBe(8926942);
    expect(conditionPrices($lossesFoil))->toBe(['nm' => [316, 200], 'lp' => [222, 231], 'mp' => [316, null]]);

    $ahri = printingFor('Ahri, Alluring', '066/298', 'foil');
    expect($ahri->tcgplayer_id)->toBe(8925997);
    expect(conditionPrices($ahri))->toBe(['nm' => [56, 25], 'lp' => [120, 30], 'mp' => [78, null]]);
});

test('heavily played and damaged rows are ignored', function () {
    $tmp = writePricingCsv('pricing-hp-damaged.csv', [
        '"9200001","Magic","Ignored Set","Only Played","","1","R","Heavily Played","1.00","","","0.90","0","0","",""',
        '"9200002","Magic","Ignored Set","Only Played","","1","R","Damaged Foil","0.50","","","0.40","0","0","",""',
        '"9200003","Lorcana TCG","Kept Set","Mixed","","2","R","Near Mint","3.00","","","2.80","0","0","",""',
        '"9200004","Lorcana TCG","Kept Set","Mixed","","2","R","Heavily Played","1.00","","","0.90","0","0","",""',
        '"9200005","Lorcana TCG","Kept Set","Mixed","","2","R","Damaged","0.10","","","0.05","0","0","",""',
    ]);

    importFixture($tmp);

    expect(Product::where('name', 'Magic')->exists())->toBeFalse();
    expect(Set::where('name', 'Ignored Set')->exists())->toBeFalse();
    expect(Card::where('name', 'Only Played')->exists())->toBeFalse();
    expect(Printing::whereIn('tcgplayer_id', [9200001, 9200002, 9200004, 9200005])->exists())->toBeFalse();
    expect(Product::where('name', 'Lorcana TCG')->firstOrFail()->priced_at)->not->toBeNull();
    expect(Product::count())->toBe(1);

    $mixed = printingFor('Mixed', '2', 'non-foil');
    expect($mixed->tcgplayer_id)->toBe(9200003);
    expect(conditionPrices($mixed))->toBe(['nm' => [300, 280], 'lp' => [null, null], 'mp' => [null, null]]);

    @unlink($tmp);
});

test('per-condition prices are independent of row order', function (bool $reversed) {
    $rows = threeConditionRows();
    $tmp = writePricingCsv('pricing-order.csv', $reversed ? array_reverse($rows) : $rows);

    importFixture($tmp);

    $nonFoil = printingFor('Order Card', '1', 'non-foil');
    $foil = printingFor('Order Card', '1', 'foil');

    expect($nonFoil->tcgplayer_id)->toBe(9100001);
    expect(conditionPrices($nonFoil))->toBe(['nm' => [500, 450], 'lp' => [400, 350], 'mp' => [300, 250]]);
    expect($foil->tcgplayer_id)->toBe(9100004);
    expect(conditionPrices($foil))->toBe(['nm' => [900, 850], 'lp' => [800, 750], 'mp' => [700, 650]]);

    @unlink($tmp);
})->with(['csv order' => false, 'reversed order' => true]);

test('a printing without an NM row keeps the SKU id it was inserted with', function () {
    $first = writePricingCsv('pricing-lp-only.csv', [
        '"9300002","Magic","Test Set","LP Only","","3","R","Lightly Played","4.00","","","3.50","0","0","",""',
    ]);
    $second = writePricingCsv('pricing-lp-only-new-sku.csv', [
        '"9300099","Magic","Test Set","LP Only","","3","R","Lightly Played","4.20","","","3.60","0","0","",""',
    ]);

    importFixture($first);
    importFixture($second);

    $printing = printingFor('LP Only', '3', 'non-foil');
    expect($printing->tcgplayer_id)->toBe(9300002);
    expect(conditionPrices($printing))->toBe(['nm' => [null, null], 'lp' => [420, 360], 'mp' => [null, null]]);

    @unlink($first);
    @unlink($second);
});

test('conditions flushed in different chunks do not overwrite each other', function () {
    $tmp = writePricingCsv('pricing-chunks.csv', array_reverse(threeConditionRows()));

    importFixture($tmp, chunkSize: 1);

    $nonFoil = printingFor('Order Card', '1', 'non-foil');
    expect($nonFoil->tcgplayer_id)->toBe(9100001);
    expect(conditionPrices($nonFoil))->toBe(['nm' => [500, 450], 'lp' => [400, 350], 'mp' => [300, 250]]);
    expect(conditionPrices(printingFor('Order Card', '1', 'foil')))
        ->toBe(['nm' => [900, 850], 'lp' => [800, 750], 'mp' => [700, 650]]);

    @unlink($tmp);
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
    $snapshot = fn () => Printing::query()
        ->orderBy('card_id')
        ->orderBy('finish')
        ->get()
        ->map(fn (Printing $printing) => [$printing->card_id, $printing->finish, $printing->tcgplayer_id, conditionPrices($printing)])
        ->all();
    $before = $snapshot();

    importFixture();

    expect(Card::count())->toBe($cardCountAfterFirst);
    expect(Printing::count())->toBe($printingCountAfterFirst);
    expect(Product::count())->toBe(3);
    expect(Set::count())->toBe(8);

    $boltyn->refresh();
    expect($boltyn->name)->toBe('Boltyn');
    expect($snapshot())->toBe($before);
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
    expect($printing->lp_market_price)->toBe(9999);
    expect($printing->lp_low_price)->toBe(9800);

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
        ->expectsOutputToContain('Imported 85 rows, touched 3 product(s).')
        ->assertSuccessful();
});

test('artisan command fails on missing file', function () {
    $this->artisan('catalog:import-pricing-custom-export', ['path' => '/no/such/file.csv'])
        ->expectsOutputToContain('Cannot read CSV')
        ->assertFailed();
});

test('import clears the cached rarity options', function () {
    Cache::forever(RarityOptionsQuery::CACHE_KEY, ['stale' => []]);

    importFixture();

    expect(Cache::has(RarityOptionsQuery::CACHE_KEY))->toBeFalse();
});
