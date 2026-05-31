<?php

use App\Models\File;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\OrderImporter;
use App\Services\Orders\OrderImportInput;
use App\Services\Orders\OrderImportResult;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function replayFixture(string $name): string
{
    return base_path("tests/fixtures/orders/{$name}");
}

function singleOrderInput(?string $orderListOverride = null): OrderImportInput
{
    return new OrderImportInput(
        orderListPath: $orderListOverride ?? replayFixture('merge-orderlist.csv'),
        shippingExportPath: replayFixture('merge-shipping.csv'),
        pullSheetPath: replayFixture('merge-pullsheet.csv'),
    );
}

test('same batch uploaded twice produces zero new orders and zero updates', function () {
    $input = singleOrderInput();

    $first = (new OrderImporter)->import($input);
    expect($first->ordersInserted)->toBeGreaterThan(0);

    $second = (new OrderImporter)->import($input);

    expect($second->ordersInserted)->toBe(0);
    expect($second->ordersUpdated)->toBe(0);
});

test('status flip from Completed - Paid to Canceled updates only the status, leaving order_items untouched', function () {
    (new OrderImporter)->import(singleOrderInput());

    $itemBefore = OrderItem::firstOrFail();
    $beforeUpdatedAt = $itemBefore->updated_at;

    $tmp = sys_get_temp_dir().'/replay-flipped.csv';
    file_put_contents($tmp, str_replace(
        '"Completed - Paid"',
        '"Canceled"',
        file_get_contents(replayFixture('merge-orderlist.csv')),
    ));

    $second = (new OrderImporter)->import(singleOrderInput($tmp));

    expect($second->ordersUpdated)->toBeGreaterThan(0);

    $thomas = Order::where('tcgplayer_order_number', '623394E9-23CAFE-565FC')->firstOrFail();
    expect($thomas->tcgplayer_status)->toBe('Canceled');

    $itemAfter = OrderItem::firstOrFail();
    expect($itemAfter->updated_at->equalTo($beforeUpdatedAt))->toBeTrue();

    @unlink($tmp);
});

test('tracking number added on second import updates tracking_number and carrier in place', function () {
    (new OrderImporter)->import(singleOrderInput());

    $thomasBefore = Order::where('tcgplayer_order_number', '623394E9-23CAFE-565FC')->firstOrFail();
    expect($thomasBefore->tracking_number)->toBeNull();

    $tmp = sys_get_temp_dir().'/replay-shipping-with-tracking.csv';
    file_put_contents($tmp, str_replace(
        '"623394E9-23CAFE-565FC","Thomas","Birch","34 Horton Heights Drive","","Newfield","NY","14867","US","2025-11-14","0.07","Standard (7-10 days)","1","6.90","1.99","",""',
        '"623394E9-23CAFE-565FC","Thomas","Birch","34 Horton Heights Drive","","Newfield","NY","14867","US","2025-11-14","0.07","Standard (7-10 days)","1","6.90","1.99","9405511899223344556677","USPS"',
        file_get_contents(replayFixture('merge-shipping.csv')),
    ));

    (new OrderImporter)->import(new OrderImportInput(
        orderListPath: replayFixture('merge-orderlist.csv'),
        shippingExportPath: $tmp,
        pullSheetPath: replayFixture('merge-pullsheet.csv'),
    ));

    $thomasAfter = $thomasBefore->refresh();
    expect($thomasAfter->tracking_number)->toBe('9405511899223344556677');
    expect($thomasAfter->carrier)->toBe('USPS');

    @unlink($tmp);
});

test('a new order appearing only in a later batch is inserted', function () {
    $input = new OrderImportInput(
        orderListPath: replayFixture('merge-orderlist.csv'),
        shippingExportPath: replayFixture('merge-shipping.csv'),
        pullSheetPath: replayFixture('merge-pullsheet.csv'),
        packingSlipPdfPath: replayFixture('packing-slips-sample.pdf'),
    );

    $result = (new OrderImporter)->import($input);
    expect($result->ordersInserted)->toBe(2);
});

test('null line prices stay null on re-import even when PDF is now provided ("never refill")', function () {
    (new OrderImporter)->import(singleOrderInput());

    $line = OrderItem::firstOrFail();
    expect($line->unit_price)->toBeNull();

    (new OrderImporter)->import(new OrderImportInput(
        orderListPath: replayFixture('merge-orderlist.csv'),
        shippingExportPath: replayFixture('merge-shipping.csv'),
        pullSheetPath: replayFixture('merge-pullsheet.csv'),
        packingSlipPdfPath: replayFixture('packing-slips-sample.pdf'),
    ));

    expect($line->refresh()->unit_price)->toBeNull();
});

test('re-uploading the same file produces a fresh files row with a fresh ULID-based path', function () {
    (new OrderImporter)->import(singleOrderInput());
    $firstPath = File::firstWhere('original_filename', 'merge-orderlist.csv')?->file_path;

    (new OrderImporter)->import(singleOrderInput());

    $rows = File::where('original_filename', 'merge-orderlist.csv')->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('file_path')->unique())->toHaveCount(2);
});

test('summaryLine permutation: only-new without unmatched suffix', function () {
    $r = new OrderImportResult(ordersInserted: 3);
    expect($r->summaryLine())->toBe('Imported 3 orders (3 new, 0 updated).');
});

test('summaryLine permutation: only-updated without unmatched suffix', function () {
    $r = new OrderImportResult(ordersUpdated: 4);
    expect($r->summaryLine())->toBe('Imported 4 orders (0 new, 4 updated).');
});

test('summaryLine permutation: mixed inserted and updated', function () {
    $r = new OrderImportResult(
        ordersInserted: 2,
        ordersUpdated: 1,
    );
    expect($r->summaryLine())->toBe('Imported 3 orders (2 new, 1 updated).');
});

test('summaryLine permutation: zero orders imported', function () {
    $r = new OrderImportResult;
    expect($r->summaryLine())->toBe('Imported 0 orders (0 new, 0 updated).');
});
