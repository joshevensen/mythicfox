<?php

use App\Services\Orders\Parsers\PackingSlipLine;
use App\Services\Orders\Parsers\PackingSlipPdfParser;

function packingSlipFixture(): string
{
    return base_path('tests/fixtures/orders/packing-slips-sample.pdf');
}

test('parses every page of the 25-page real fixture and yields PackingSlipLine entries', function () {
    $lines = (new PackingSlipPdfParser)->parse(packingSlipFixture());

    expect($lines)->not->toBeEmpty();
    expect($lines->first())->toBeInstanceOf(PackingSlipLine::class);
});

test('extracts the order number from each parsed line, uppercased', function () {
    $lines = (new PackingSlipPdfParser)->parse(packingSlipFixture());

    foreach ($lines as $line) {
        expect($line->tcgplayerOrderNumber)
            ->toMatch('/^[0-9A-F]+(-[0-9A-F]+)+$/');
    }
});

test('parses the first page line item with the wrapped condition', function () {
    $lines = (new PackingSlipPdfParser)->parse(packingSlipFixture());

    $boltyn = $lines->where('tcgplayerOrderNumber', '623394E9-23CAFE-565FC')->first();
    expect($boltyn)->not->toBeNull();
    expect($boltyn->quantity)->toBe(1);
    expect($boltyn->productLine)->toBe('Flesh & Blood TCG');
    expect($boltyn->setName)->toBe('Crucible of War');
    expect($boltyn->productName)->toBe('Beast Within');
    expect($boltyn->number)->toBe('CRU007');
    expect($boltyn->rarity)->toBe('Majestic');
    expect($boltyn->condition)->toBe('Near Mint Unlimited Edition Normal');
    expect($boltyn->unitPrice)->toBe(690);
    expect($boltyn->totalPrice)->toBe(690);
});

test('preserves a hyphen inside a product name', function () {
    $lines = (new PackingSlipPdfParser)->parse(packingSlipFixture());

    $calhoun = $lines->where('productName', 'Calhoun - Marine Sergeant')->first();
    expect($calhoun)->not->toBeNull();
    expect($calhoun->productLine)->toBe('Lorcana TCG');
});

test('emits multiple line items per page when present', function () {
    $lines = (new PackingSlipPdfParser)->parse(packingSlipFixture());

    $byOrder = $lines->groupBy('tcgplayerOrderNumber');
    $multiLineOrders = $byOrder->filter(fn ($items) => $items->count() > 1);

    expect($multiLineOrders)->not->toBeEmpty();
});

test('decimal-to-cents conversion is exact', function () {
    $lines = (new PackingSlipPdfParser)->parse(packingSlipFixture());

    $boltyn = $lines->where('tcgplayerOrderNumber', '623394E9-23CAFE-565FC')->first();
    expect($boltyn->unitPrice)->toBeInt()->toBe(690);
    expect($boltyn->totalPrice)->toBeInt()->toBe(690);
});

test('parsing a valid fixture does not throw', function () {
    expect(fn () => (new PackingSlipPdfParser)->parse(packingSlipFixture()))
        ->not->toThrow(Throwable::class);
});

test('a missing PDF raises a RuntimeException', function () {
    expect(fn () => (new PackingSlipPdfParser)->parse('/no/such/file.pdf'))
        ->toThrow(RuntimeException::class);
});

test('throws RuntimeException with install instructions when pdftotext is missing', function () {
    $parser = new class extends PackingSlipPdfParser
    {
        protected function findPdftotext(): ?string
        {
            return null;
        }
    };

    expect(fn () => $parser->parse(packingSlipFixture()))
        ->toThrow(RuntimeException::class, 'poppler-utils');
});
