<?php

use App\Models\Card;
use App\Models\Inventory;
use App\Services\Catalog\CatalogUpserter;
use App\Services\Catalog\MyPricingImporter;
use App\Services\Catalog\MyPricingImportMode;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function myPricingFixture(): string
{
    return base_path('tests/fixtures/catalog/mypricing-sample.csv');
}

function bootstrapImport(?string $path = null, bool $force = false): void
{
    $importer = new MyPricingImporter(new CatalogUpserter);
    $importer->import($path ?? myPricingFixture(), MyPricingImportMode::Bootstrap, force: $force);
}

function syncOverridesToCsvMarketplacePrices(): void
{
    $rows = [
        4941626 => 25,
        4941566 => 25,
        4941700 => 1185,
        5012345 => 250,
    ];
    foreach ($rows as $tcgplayerId => $cents) {
        $card = Card::where('tcgplayer_id', $tcgplayerId)->firstOrFail();
        $card->inventory->update(['override_price' => $cents]);
    }
}

test('bootstrap upserts catalog and creates inventory rows with quantities from Total Quantity', function () {
    bootstrapImport();

    expect(Card::count())->toBe(4);
    expect(Inventory::count())->toBe(4);

    $boltyn = Card::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($boltyn->inventory->quantity)->toBe(1);

    $blue = Card::where('tcgplayer_id', 4941626)->firstOrFail();
    expect($blue->inventory->quantity)->toBe(4);
});

test('bootstrap does not populate override_price from TCG Marketplace Price', function () {
    bootstrapImport();

    $boltyn = Card::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($boltyn->inventory->override_price)->toBeNull();
    expect($boltyn->inventory->calculated_price)->toBeNull();
});

test('bootstrap refuses to run twice without force', function () {
    bootstrapImport();

    expect(fn () => bootstrapImport())->toThrow(RuntimeException::class, 'inventory already has rows');
});

test('bootstrap with force overwrites quantities without doubling', function () {
    bootstrapImport();
    $boltyn = Card::where('tcgplayer_id', 4941700)->firstOrFail();
    expect($boltyn->inventory->quantity)->toBe(1);

    bootstrapImport(force: true);

    $boltyn->refresh();
    expect($boltyn->inventory->quantity)->toBe(1);
    expect(Inventory::count())->toBe(4);
});

test('reconciliation flags quantity discrepancies and writes nothing', function () {
    bootstrapImport();

    syncOverridesToCsvMarketplacePrices();

    $blue = Card::where('tcgplayer_id', 4941626)->firstOrFail();
    $blue->inventory->update(['quantity' => 99]);
    $blueQtyBefore = $blue->inventory->quantity;

    $importer = new MyPricingImporter(new CatalogUpserter);
    $result = $importer->import(myPricingFixture(), MyPricingImportMode::Reconciliation);

    expect($result->rowsProcessed)->toBe(4);
    expect($result->discrepancies)->toHaveCount(1);
    expect($result->discrepancies[0]['tcgplayer_id'])->toBe(4941626);
    expect($result->discrepancies[0]['local_quantity'])->toBe(99);
    expect($result->discrepancies[0]['csv_quantity'])->toBe(4);

    $blue->refresh();
    expect($blue->inventory->quantity)->toBe($blueQtyBefore);
});

test('reconciliation flags price discrepancies', function () {
    bootstrapImport();

    $boltyn = Card::where('tcgplayer_id', 4941700)->firstOrFail();
    $boltyn->inventory->update(['override_price' => 999]);

    $importer = new MyPricingImporter(new CatalogUpserter);
    $result = $importer->import(myPricingFixture(), MyPricingImportMode::Reconciliation);

    $boltynDiff = collect($result->discrepancies)->firstWhere('tcgplayer_id', 4941700);
    expect($boltynDiff)->not->toBeNull();
    expect($boltynDiff['local_effective_price'])->toBe(999);
    expect($boltynDiff['csv_marketplace_price'])->toBe(1185);
});

test('reconciliation flags rows in CSV but missing locally', function () {
    bootstrapImport();

    $card = Card::where('tcgplayer_id', 5012345)->firstOrFail();
    $card->inventory()->delete();
    $card->delete();

    $importer = new MyPricingImporter(new CatalogUpserter);
    $result = $importer->import(myPricingFixture(), MyPricingImportMode::Reconciliation);

    expect($result->missingLocally)->toContain(5012345);
});

test('reconciliation flags rows local-only when not in CSV', function () {
    bootstrapImport();

    $orphan = Card::factory()->create(['tcgplayer_id' => 7777777]);
    Inventory::factory()->create(['card_id' => $orphan->id, 'quantity' => 5]);

    $importer = new MyPricingImporter(new CatalogUpserter);
    $result = $importer->import(myPricingFixture(), MyPricingImportMode::Reconciliation);

    expect($result->localOnly)->toContain(7777777);
});

test('reconciliation persists the source file even though it writes no catalog rows', function () {
    bootstrapImport();

    $importer = new MyPricingImporter(new CatalogUpserter);
    $result = $importer->import(myPricingFixture(), MyPricingImportMode::Reconciliation);

    expect($result->file->type)->toBe('import');
    Storage::assertExists($result->file->file_path);
});

test('artisan command defaults to reconcile mode', function () {
    bootstrapImport();

    $this->artisan('catalog:import-mypricing', ['path' => myPricingFixture()])
        ->expectsOutputToContain('Reconcile: 4 rows processed')
        ->assertSuccessful();
});

test('artisan command runs bootstrap mode with --mode=bootstrap and --force', function () {
    $this->artisan('catalog:import-mypricing', [
        'path' => myPricingFixture(),
        '--mode' => 'bootstrap',
    ])
        ->expectsOutputToContain('Bootstrap: 4 rows processed, 4 inventory rows written.')
        ->assertSuccessful();

    expect(Inventory::count())->toBe(4);
});
