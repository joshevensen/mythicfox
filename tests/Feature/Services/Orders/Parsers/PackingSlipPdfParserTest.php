<?php

use App\Services\Orders\Parsers\PackingSlipLine;
use App\Services\Orders\Parsers\PackingSlipPdfParser;
use Illuminate\Support\Collection;

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

    expect(fn () => $parser->parse('/no/such/file.pdf'))
        ->toThrow(RuntimeException::class, 'poppler-utils');
});

// ── Intelligent Mail barcode (IMb) ────────────────────────────

function imbPackingSlipFixture(): string
{
    return base_path('tests/fixtures/orders/packing-slips-imb-sample.pdf');
}

test('IMb fixture: every line carries its order\'s 65-character IMb barcode', function () {
    $lines = (new PackingSlipPdfParser)->parse(imbPackingSlipFixture());

    $byOrder = $lines->groupBy('tcgplayerOrderNumber');

    expect($byOrder->keys()->sort()->values()->all())
        ->toBe(['623394E9-44ECCD-BE247', '623394E9-85D84F-CA3FC']);

    expect($byOrder['623394E9-44ECCD-BE247'])->toHaveCount(2);
    expect($byOrder['623394E9-85D84F-CA3FC'])->toHaveCount(1);

    foreach ($byOrder['623394E9-44ECCD-BE247'] as $line) {
        expect($line->imbBarcode)->toBe('FDATDDDFTFDFDATFTAFFTFFDFTFTTADADFFTTTTFTATAFDFTTDTFDFAAFAFTFTADF');
    }

    foreach ($byOrder['623394E9-85D84F-CA3FC'] as $line) {
        expect($line->imbBarcode)->toBe('FFFFTDDAFDFAATDFDTFAADDTDFFATTATDTDTDDFAFFDDAFFFADDFFATFDFDATATDF');
    }
});

test('IMb fixture: the barcode row does not break line-item parsing (Blizzard ELE147)', function () {
    $lines = (new PackingSlipPdfParser)->parse(imbPackingSlipFixture());

    $blizzard = $lines->firstWhere('number', 'ELE147');
    expect($blizzard)->not->toBeNull();
    expect($blizzard->tcgplayerOrderNumber)->toBe('623394E9-44ECCD-BE247');
    expect($blizzard->quantity)->toBe(1);
    expect($blizzard->productLine)->toBe('Flesh & Blood TCG');
    expect($blizzard->setName)->toBe('Tales of Aria');
    expect($blizzard->productName)->toBe('Blizzard');
    expect($blizzard->rarity)->toBe('Majestic');
    expect($blizzard->condition)->toBe('Near Mint 1st Edition Normal');
    expect($blizzard->unitPrice)->toBe(312);
    expect($blizzard->totalPrice)->toBe(312);

    $pulse = $lines->firstWhere('number', 'ELE114');
    expect($pulse)->not->toBeNull();
    expect($pulse->tcgplayerOrderNumber)->toBe('623394E9-44ECCD-BE247');
    expect($pulse->productName)->toBe('Pulse of Isenloft');
    expect($pulse->unitPrice)->toBe(295);
});

test('IMb fixture: number, rarity and condition wrapped onto a continuation line are parsed (PEN227)', function () {
    $lines = (new PackingSlipPdfParser)->parse(imbPackingSlipFixture());

    $crown = $lines->firstWhere('tcgplayerOrderNumber', '623394E9-85D84F-CA3FC');
    expect($crown)->not->toBeNull();
    expect($crown->quantity)->toBe(1);
    expect($crown->productLine)->toBe('Flesh & Blood TCG');
    expect($crown->setName)->toBe('Compendium of Rathe');
    expect($crown->productName)->toBe('Crown Of Frozen Thoughts');
    expect($crown->number)->toBe('PEN227');
    expect($crown->rarity)->toBe('Majestic');
    expect($crown->condition)->toBe('Near Mint');
    expect($crown->unitPrice)->toBe(44);
    expect($crown->totalPrice)->toBe(44);
});

test('old-format fixture without barcodes yields null imbBarcode on every line', function () {
    $lines = (new PackingSlipPdfParser)->parse(packingSlipFixture());

    expect($lines)->not->toBeEmpty();
    foreach ($lines as $line) {
        expect($line->imbBarcode)->toBeNull();
    }
});

/**
 * Runs the private text parser on synthetic pdftotext output so page and
 * order sequences the real fixtures don't contain can be exercised.
 *
 * @return Collection<int, PackingSlipLine>
 */
function parseImbText(string $text): Collection
{
    return (fn (string $text) => $this->parseText($text))->call(new PackingSlipPdfParser, $text);
}

function imbItemLine(string $number): string
{
    return "       1          Flesh & Blood TCG - Tales of Aria - Blizzard - #{$number} - Majestic - Near Mint                 \$3.12           \$3.12";
}

test('a barcode does not leak onto the next order when that order has none', function () {
    $barcode = str_repeat('FADT', 16).'F';
    $text = implode("\n", [
        'Order Number: 623394E9-AAAAAA-AAAAA',
        "  {$barcode}",
        imbItemLine('ELE001'),
        'Order Number: 623394E9-AAAAAA-AAAAA                Page 1 of 1',
        'Order Number: 623394E9-BBBBBB-BBBBB',
        imbItemLine('ELE002'),
        'Order Number: 623394E9-BBBBBB-BBBBB                Page 1 of 1',
    ]);

    $lines = parseImbText($text);

    expect($lines->firstWhere('number', 'ELE001')->imbBarcode)->toBe($barcode);
    expect($lines->firstWhere('number', 'ELE002')->imbBarcode)->toBeNull();
});

test('a multi-page order keeps its barcode on pages after the first', function () {
    $barcode = str_repeat('DTAF', 16).'D';
    $text = implode("\n", [
        'Order Number: 623394E9-CCCCCC-CCCCC',
        "  {$barcode}",
        imbItemLine('ELE003'),
        'Order Number: 623394E9-CCCCCC-CCCCC                Page 1 of 2',
        'Order Number: 623394E9-CCCCCC-CCCCC',
        imbItemLine('ELE004'),
        'Order Number: 623394E9-CCCCCC-CCCCC                Page 2 of 2',
    ]);

    $lines = parseImbText($text);

    expect($lines)->toHaveCount(2);
    expect($lines->firstWhere('number', 'ELE004')->imbBarcode)->toBe($barcode);
});
