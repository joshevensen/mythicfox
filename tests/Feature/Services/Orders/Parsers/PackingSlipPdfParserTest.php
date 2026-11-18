<?php

use App\Exceptions\OrderImport\WrongSellerException;
use App\Services\Orders\Parsers\PackingSlipLine;
use App\Services\Orders\Parsers\PackingSlipPdfParser;
use App\Services\Orders\SellerIdValidator;
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
        expect($line->imbBarcode)->toBe('DFTFDATTDADTTDAATFFAFTDFFAATADFDFFFTFATFAAADADDDATTAFFFTADFATTTDD');
    }

    foreach ($byOrder['623394E9-85D84F-CA3FC'] as $line) {
        expect($line->imbBarcode)->toBe('DDFFDDDFATDDDDTFTFDFAADTATATDFFFAAADTADFFTFTDAADDFTFDFDFFDDFFTDTD');
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

// ── Nov 13 layout (footer-only order label, three-line items) ──────────

/**
 * A Nov 13-layout item: description lines above and below a bare qty+prices row.
 *
 * @param  list<string>  $above
 * @param  list<string>  $below
 * @return list<string>
 */
function nov13Item(array $above, int $quantity, string $unitPrice, string $totalPrice, array $below): array
{
    $indent = fn (string $line): string => '         '.$line;

    return [
        ...array_map($indent, $above),
        '     '.$quantity.str_repeat(' ', 66).'$'.$unitPrice.'             $'.$totalPrice,
        ...array_map($indent, $below),
    ];
}

/**
 * @return list<string>
 */
function nov13ChannelMountItem(string $number = 'ELE117'): array
{
    return nov13Item(
        ['Flesh & Blood TCG - Tales of Aria - Channel Mount Heroic -'],
        1,
        '0.50',
        '0.50',
        ["#{$number} - Majestic - Near Mint 1st Edition Normal"],
    );
}

/**
 * One synthetic pdftotext page in the Nov 13 shape. The bare order number sits
 * on line 1 and the only labeled `Order Number:` occurrences are in $footerLabels.
 *
 * @param  list<string>  $itemLines
 * @param  list<string>  $footerLabels
 */
function nov13Page(string $bareOrderNumber, ?string $barcode, array $itemLines, array $footerLabels, string $totalLine = '$0.50'): string
{
    $lines = [
        str_repeat(' ', 104).$bareOrderNumber,
        '',
        ...($barcode === null ? [] : ["        {$barcode}"]),
        '        BUYER 99 TEST',
        '        199 EXAMPLE STREET',
        '        SPRINGFIELD, IL 62701',
        '',
        '                                                    Fold Line',
        '',
        'ORDER DATE        SHIPPING METHOD        BUYER        SELLER',
        'Nov 12, 2026      Standard (7-10 days)   BUYER 99 TEST  Mythic Fox Games',
        '',
        ' QTY     DESCRIPTION                                                   PRICE             TOTAL',
        '',
        ...$itemLines,
        '     1      Total                                                                           '.$totalLine,
        '',
        'For Any Questions about Your Order',
        '',
    ];

    foreach ($footerLabels as $label) {
        $lines[] = "Order Number: {$label}                                                      Page 1 of 1";
    }

    return implode("\n", $lines);
}

function nov13Fixture(): string
{
    return base_path('tests/fixtures/orders/packing-slips-nov13-sample.pdf');
}

test('Nov 13 layout: an order number that appears only in the page footer applies to the barcode and items above it', function () {
    $barcode = 'TTDT'.str_repeat('FADT', 15).'F';

    $lines = parseImbText(nov13Page(
        '623394E9-AAAAAA-AAAAA',
        $barcode,
        nov13ChannelMountItem(),
        ['623394E9-AAAAAA-AAAAA'],
    ));

    expect($lines)->toHaveCount(1);

    $line = $lines->first();
    expect($line->tcgplayerOrderNumber)->toBe('623394E9-AAAAAA-AAAAA');
    expect($line->imbBarcode)->toBe($barcode);
    expect($line->productLine)->toBe('Flesh & Blood TCG');
    expect($line->setName)->toBe('Tales of Aria');
    expect($line->productName)->toBe('Channel Mount Heroic');
    expect($line->number)->toBe('ELE117');
    expect($line->rarity)->toBe('Majestic');
    expect($line->condition)->toBe('Near Mint 1st Edition Normal');
    expect($line->quantity)->toBe(1);
    expect($line->unitPrice)->toBe(50);
    expect($line->totalPrice)->toBe(50);
});

test('Nov 13 layout: the three-line item path wins over the same-line match on the qty-only row', function () {
    $text = 'Order Number: 623394E9-AAAAAA-AAAAA'."\n".nov13Page(
        '623394E9-AAAAAA-AAAAA',
        null,
        nov13ChannelMountItem(),
        ['623394E9-AAAAAA-AAAAA'],
    );

    $lines = parseImbText($text);

    expect($lines)->toHaveCount(1);
    expect($lines->first()->number)->toBe('ELE117');
    expect($lines->first()->productName)->toBe('Channel Mount Heroic');
    expect($lines->first()->setName)->toBe('Tales of Aria');
    expect($lines->first()->condition)->toBe('Near Mint 1st Edition Normal');
});

test('Nov 13 fixture: lines are grouped per order with each page\'s barcode', function () {
    $lines = (new PackingSlipPdfParser)->parse(nov13Fixture());

    $byOrder = $lines->groupBy('tcgplayerOrderNumber');

    expect($byOrder->keys()->sort()->values()->all())
        ->toBe(['623394E9-13A0B1-C0FFE', '623394E9-13B2C3-BEEF2']);
    expect($byOrder['623394E9-13A0B1-C0FFE'])->toHaveCount(2);
    expect($byOrder['623394E9-13B2C3-BEEF2'])->toHaveCount(1);

    foreach ($byOrder['623394E9-13A0B1-C0FFE'] as $line) {
        expect($line->imbBarcode)->toBe('FDFFDTFFTTFAADFATDFTTFDTATDTTTAAFTDDDDTTTDATTTFFFAFTTAFFFDTATFDAD');
    }

    foreach ($byOrder['623394E9-13B2C3-BEEF2'] as $line) {
        expect($line->imbBarcode)->toBe('TTATADFDFDDFDDDATDDTTDTFFAAFTFDFTTTDADATFDTAAADADFTFFFFTDDADAADAF');
    }
});

test('Nov 13 fixture: a multi-item order splits descriptions between adjacent qty rows (ELE117, ELE147)', function () {
    $lines = (new PackingSlipPdfParser)->parse(nov13Fixture());

    $channelMount = $lines->firstWhere('number', 'ELE117');
    expect($channelMount)->not->toBeNull();
    expect($channelMount->tcgplayerOrderNumber)->toBe('623394E9-13A0B1-C0FFE');
    expect($channelMount->productName)->toBe('Channel Mount Heroic');
    expect($channelMount->rarity)->toBe('Majestic');
    expect($channelMount->condition)->toBe('Near Mint 1st Edition Normal');
    expect($channelMount->quantity)->toBe(1);
    expect($channelMount->unitPrice)->toBe(50);
    expect($channelMount->totalPrice)->toBe(50);

    $blizzard = $lines->firstWhere('number', 'ELE147');
    expect($blizzard)->not->toBeNull();
    expect($blizzard->tcgplayerOrderNumber)->toBe('623394E9-13A0B1-C0FFE');
    expect($blizzard->productName)->toBe('Blizzard');
    expect($blizzard->setName)->toBe('Tales of Aria');
    expect($blizzard->rarity)->toBe('Majestic');
    expect($blizzard->condition)->toBe('Near Mint 1st Edition Normal');
    expect($blizzard->quantity)->toBe(2);
    expect($blizzard->unitPrice)->toBe(312);
    expect($blizzard->totalPrice)->toBe(624);
});

test('Nov 13 fixture: a long description wrapped two lines above and two below the qty row is joined (PEN227)', function () {
    $lines = (new PackingSlipPdfParser)->parse(nov13Fixture());

    $crown = $lines->firstWhere('number', 'PEN227');
    expect($crown)->not->toBeNull();
    expect($crown->tcgplayerOrderNumber)->toBe('623394E9-13B2C3-BEEF2');
    expect($crown->productLine)->toBe('Flesh & Blood TCG');
    expect($crown->setName)->toBe('Compendium of Rathe');
    expect($crown->productName)->toBe('Crown Of Frozen Thoughts (Marvel Cold Foil Extended Art Variant)');
    expect($crown->rarity)->toBe('Majestic');
    expect($crown->condition)->toBe('Near Mint Rainbow Foil');
    expect($crown->quantity)->toBe(1);
    expect($crown->unitPrice)->toBe(44);
    expect($crown->totalPrice)->toBe(44);
});

test('Nov 13 layout: a barcode-less order on the next page does not inherit the previous page\'s barcode', function () {
    $barcode = str_repeat('FADT', 16).'F';

    $text = implode("\f", [
        nov13Page('623394E9-AAAAAA-AAAAA', $barcode, nov13ChannelMountItem('ELE001'), ['623394E9-AAAAAA-AAAAA']),
        nov13Page('623394E9-BBBBBB-BBBBB', null, nov13ChannelMountItem('ELE002'), ['623394E9-BBBBBB-BBBBB']),
    ]);

    $lines = parseImbText($text);

    expect($lines)->toHaveCount(2);
    expect($lines->firstWhere('number', 'ELE001')->tcgplayerOrderNumber)->toBe('623394E9-AAAAAA-AAAAA');
    expect($lines->firstWhere('number', 'ELE001')->imbBarcode)->toBe($barcode);
    expect($lines->firstWhere('number', 'ELE002')->tcgplayerOrderNumber)->toBe('623394E9-BBBBBB-BBBBB');
    expect($lines->firstWhere('number', 'ELE002')->imbBarcode)->toBeNull();
});

test('Nov 13 layout: an order spanning two pages keeps page 1\'s barcode on page 2', function () {
    $barcode = str_repeat('DTAF', 16).'D';

    $text = implode("\f", [
        nov13Page('623394E9-CCCCCC-CCCCC', $barcode, nov13ChannelMountItem('ELE003'), ['623394E9-CCCCCC-CCCCC']),
        nov13Page('623394E9-CCCCCC-CCCCC', null, nov13ChannelMountItem('ELE004'), ['623394E9-CCCCCC-CCCCC']),
    ]);

    $lines = parseImbText($text);

    expect($lines)->toHaveCount(2);
    foreach (['ELE003', 'ELE004'] as $number) {
        $line = $lines->firstWhere('number', $number);
        expect($line)->not->toBeNull();
        expect($line->tcgplayerOrderNumber)->toBe('623394E9-CCCCCC-CCCCC');
        expect($line->imbBarcode)->toBe($barcode);
    }
});

test('a page without any Order Number label yields no lines, even after a labeled page', function () {
    $labeledPage = implode("\n", [
        'Order Number: 623394E9-AAAAAA-AAAAA',
        imbItemLine('ELE020'),
        'Order Number: 623394E9-AAAAAA-AAAAA                Page 1 of 1',
    ]);

    $unlabeledPage = implode("\n", [
        'Some unrelated page',
        imbItemLine('ELE022'),
        '',
        ...nov13ChannelMountItem('ELE021'),
    ]);

    $lines = parseImbText($labeledPage."\f".$unlabeledPage);

    expect($lines->pluck('number')->all())->toBe(['ELE020']);
});

test('a page with two distinct labeled order numbers is not pre-resolved to the first label', function () {
    $text = implode("\n", [
        ' QTY     DESCRIPTION                                                   PRICE             TOTAL',
        '',
        ...nov13ChannelMountItem('ELE010'),
        '',
        'Order Number: 623394E9-AAAAAA-AAAAA                Page 1 of 1',
        'Order Number: 623394E9-BBBBBB-BBBBB',
        imbItemLine('ELE011'),
    ]);

    $lines = parseImbText($text);

    expect($lines->firstWhere('number', 'ELE010'))->toBeNull();
    expect($lines)->toHaveCount(1);
    expect($lines->firstWhere('number', 'ELE011')->tcgplayerOrderNumber)->toBe('623394E9-BBBBBB-BBBBB');
});

test('Nov 13 layout: seller validation runs once per distinct order across pages', function () {
    $validator = new class extends SellerIdValidator
    {
        public int $calls = 0;

        public function assertValid(string $orderNumber): void
        {
            $this->calls++;
            parent::assertValid($orderNumber);
        }
    };

    $text = implode("\f", [
        nov13Page('623394E9-AAAAAA-AAAAA', null, nov13ChannelMountItem('ELE031'), ['623394E9-AAAAAA-AAAAA']),
        nov13Page('623394E9-AAAAAA-AAAAA', null, nov13ChannelMountItem('ELE032'), ['623394E9-AAAAAA-AAAAA']),
        nov13Page('623394E9-BBBBBB-BBBBB', null, nov13ChannelMountItem('ELE033'), ['623394E9-BBBBBB-BBBBB']),
    ]);

    $lines = (fn (string $text) => $this->parseText($text))->call(new PackingSlipPdfParser($validator), $text);

    expect($lines)->toHaveCount(3);
    expect($validator->calls)->toBe(2);
});

test('Nov 13 layout: a wrong seller prefix that appears only in the footer label throws', function () {
    config(['services.tcgplayer.seller_id' => '623394E9']);

    $text = nov13Page('DEADBEEF-AAAAAA-AAAAA', null, nov13ChannelMountItem(), ['DEADBEEF-AAAAAA-AAAAA']);

    expect(fn () => parseImbText($text))->toThrow(WrongSellerException::class);
});

test('Nov 13 layout: a deep-indented Order Number label directly above a qty row is not taken as description', function () {
    $itemLines = [
        '          Order Number: 623394E9-AAAAAA-AAAAA',
        ...nov13Item(
            [],
            1,
            '0.50',
            '0.50',
            [
                'Flesh & Blood TCG - Tales of Aria - Channel Mount Heroic -',
                '#ELE117 - Majestic - Near Mint 1st Edition Normal',
            ],
        ),
    ];

    $lines = parseImbText(nov13Page('623394E9-AAAAAA-AAAAA', null, $itemLines, ['623394E9-AAAAAA-AAAAA']));

    expect($lines)->toHaveCount(1);

    $line = $lines->first();
    expect($line->tcgplayerOrderNumber)->toBe('623394E9-AAAAAA-AAAAA');
    expect($line->productLine)->toBe('Flesh & Blood TCG');
    expect($line->setName)->toBe('Tales of Aria');
    expect($line->productName)->toBe('Channel Mount Heroic');
    expect($line->number)->toBe('ELE117');
    expect($line->condition)->toBe('Near Mint 1st Edition Normal');
});

test('Nov 13 layout: a deep-indented line containing a $ amount above an item is not prepended to its description', function () {
    $itemLines = [
        '            Shipping $0.00',
        ...nov13ChannelMountItem(),
    ];

    $lines = parseImbText(nov13Page('623394E9-AAAAAA-AAAAA', null, $itemLines, ['623394E9-AAAAAA-AAAAA']));

    expect($lines)->toHaveCount(1);

    $line = $lines->first();
    expect($line->productLine)->toBe('Flesh & Blood TCG');
    expect($line->setName)->toBe('Tales of Aria');
    expect($line->productName)->toBe('Channel Mount Heroic');
    expect($line->number)->toBe('ELE117');
    expect($line->rarity)->toBe('Majestic');
    expect($line->condition)->toBe('Near Mint 1st Edition Normal');
});
