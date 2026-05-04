<?php

use App\Exceptions\OrderImport\InvalidPullSheetException;
use App\Services\Orders\Parsers\PullSheetParser;

function pullSheetFixture(): string
{
    return base_path('tests/fixtures/orders/pullsheet-sample.csv');
}

test('multi-order Order Quantity emits one line item per (order, qty) pair', function () {
    $items = (new PullSheetParser)->parse(pullSheetFixture());

    $brainstorm = $items->where('productName', 'Brainstorm');
    expect($brainstorm)->toHaveCount(2);

    $byOrder = $brainstorm->keyBy('tcgplayerOrderNumber');
    expect($byOrder['623394E9-23CAFE-565FC']->quantity)->toBe(2);
    expect($byOrder['623394E9-805EEF-0F0CC']->quantity)->toBe(1);
});

test('single-order Order Quantity (no pipe) emits one line item', function () {
    $items = (new PullSheetParser)->parse(pullSheetFixture());

    $counterspell = $items->where('productName', 'Counterspell')->first();
    expect($counterspell->tcgplayerOrderNumber)->toBe('623394E9-23CAFE-565FC');
    expect($counterspell->quantity)->toBe(1);
});

test('whitespace tolerance: A:1|B:2 and A:1 | B:2 are equivalent', function () {
    $base = "Product Line,Product Name,Condition,Number,Set,Rarity,Quantity,Main Photo URL,Set Release Date,SkuId,Order Quantity\n";

    $tightTmp = sys_get_temp_dir().'/pullsheet-tight.csv';
    file_put_contents($tightTmp, $base.'"Magic","X","Near Mint","1","S","C","2","","","100","623394E9-AAA-001:1|623394E9-BBB-002:1"'."\n");

    $looseTmp = sys_get_temp_dir().'/pullsheet-loose.csv';
    file_put_contents($looseTmp, $base.'"Magic","X","Near Mint","1","S","C","2","","","100","623394E9-AAA-001:1 | 623394E9-BBB-002:1"'."\n");

    $tight = (new PullSheetParser)->parse($tightTmp);
    $loose = (new PullSheetParser)->parse($looseTmp);

    expect($tight->pluck('tcgplayerOrderNumber')->all())
        ->toBe($loose->pluck('tcgplayerOrderNumber')->all());

    @unlink($tightTmp);
    @unlink($looseTmp);
});

test('compound condition strings preserved verbatim', function () {
    $items = (new PullSheetParser)->parse(pullSheetFixture());

    $brainstorm = $items->where('productName', 'Brainstorm')->first();
    expect($brainstorm->condition)->toBe('Near Mint Foil');

    $boltyn = $items->where('productName', 'Boltyn')->first();
    expect($boltyn->condition)->toBe('Near Mint');
});

test('tcgplayer_sku_id is integer; empty values null', function () {
    $items = (new PullSheetParser)->parse(pullSheetFixture());

    foreach ($items as $item) {
        expect($item->tcgplayerSkuId)->toBeInt();
    }

    $tmp = sys_get_temp_dir().'/pullsheet-empty-sku.csv';
    file_put_contents($tmp, <<<'CSV'
Product Line,Product Name,Condition,Number,Set,Rarity,Quantity,Main Photo URL,Set Release Date,SkuId,Order Quantity
"Magic","X","Near Mint","1","S","C","1","","","","623394E9-AAA-001:1"
CSV);

    $items = (new PullSheetParser)->parse($tmp);
    expect($items->first()->tcgplayerSkuId)->toBeNull();

    @unlink($tmp);
});

test('uppercases tcgplayer_order_number on every emitted line', function () {
    $tmp = sys_get_temp_dir().'/pullsheet-lower.csv';
    file_put_contents($tmp, <<<'CSV'
Product Line,Product Name,Condition,Number,Set,Rarity,Quantity,Main Photo URL,Set Release Date,SkuId,Order Quantity
"Magic","X","Near Mint","1","S","C","2","","","100","623394e9-aaa-001:1 | 623394E9-bbb-002:1"
CSV);

    $items = (new PullSheetParser)->parse($tmp);
    foreach ($items as $item) {
        expect($item->tcgplayerOrderNumber)->toBe(strtoupper($item->tcgplayerOrderNumber));
    }

    @unlink($tmp);
});

test('rows with empty Order Quantity are skipped (TCGplayer summary row)', function () {
    $tmp = sys_get_temp_dir().'/pullsheet-summary-row.csv';
    file_put_contents($tmp, <<<'CSV'
Product Line,Product Name,Condition,Number,Set,Rarity,Quantity,Main Photo URL,Set Release Date,SkuId,Order Quantity
"Magic","Counterspell","Near Mint","25","S","C","1","","","100","623394E9-AAA-001:1"
"","Total","","","","","2","","","",""
CSV);

    $items = (new PullSheetParser)->parse($tmp);

    expect($items)->toHaveCount(1);
    expect($items->first()->productName)->toBe('Counterspell');

    @unlink($tmp);
});

test('malformed Order Quantity raises InvalidPullSheetException with row number', function () {
    $tmp = sys_get_temp_dir().'/pullsheet-bad-qty.csv';
    file_put_contents($tmp, <<<'CSV'
Product Line,Product Name,Condition,Number,Set,Rarity,Quantity,Main Photo URL,Set Release Date,SkuId,Order Quantity
"Magic","X","Near Mint","1","S","C","1","","","100","NO-COLON-HERE"
CSV);

    expect(fn () => (new PullSheetParser)->parse($tmp))
        ->toThrow(InvalidPullSheetException::class, 'Order Quantity');

    @unlink($tmp);
});

test('non-integer quantity raises InvalidPullSheetException', function () {
    $tmp = sys_get_temp_dir().'/pullsheet-bad-int.csv';
    file_put_contents($tmp, <<<'CSV'
Product Line,Product Name,Condition,Number,Set,Rarity,Quantity,Main Photo URL,Set Release Date,SkuId,Order Quantity
"Magic","X","Near Mint","1","S","C","1","","","100","623394E9-AAA-001:not-a-number"
CSV);

    expect(fn () => (new PullSheetParser)->parse($tmp))
        ->toThrow(InvalidPullSheetException::class);

    @unlink($tmp);
});

test('missing required header raises domain exception', function () {
    $tmp = sys_get_temp_dir().'/pullsheet-no-header.csv';
    file_put_contents($tmp, <<<'CSV'
Product Line,Product Name,Number,Rarity
"Magic","X","1","C"
CSV);

    expect(fn () => (new PullSheetParser)->parse($tmp))
        ->toThrow(InvalidPullSheetException::class, 'missing required header');

    @unlink($tmp);
});
