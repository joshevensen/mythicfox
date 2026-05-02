<?php

use App\Exceptions\OrderImport\InvalidShippingExportException;
use App\Services\Orders\Parsers\ShippingExportParser;

function shippingExportFixture(): string
{
    return base_path('tests/fixtures/orders/shipping-export-sample.csv');
}

test('parses every row from the canonical fixture', function () {
    $rows = (new ShippingExportParser)->parse(shippingExportFixture());

    expect($rows)->toHaveCount(3);
});

test('order date parsed as ISO Y-m-d', function () {
    $rows = (new ShippingExportParser)->parse(shippingExportFixture())->keyBy('tcgplayerOrderNumber');

    expect($rows['623394E9-23CAFE-565FC']->orderDate->toDateString())->toBe('2025-11-14');
});

test('addresses, city, and 2-letter state preserved verbatim', function () {
    $rows = (new ShippingExportParser)->parse(shippingExportFixture())->keyBy('tcgplayerOrderNumber');

    $thomas = $rows['623394E9-23CAFE-565FC'];
    expect($thomas->address1)->toBe('34 Horton Heights Drive');
    expect($thomas->city)->toBe('Newfield');
    expect($thomas->state)->toBe('NY');
    expect($thomas->postalCode)->toBe('14867');
    expect($thomas->country)->toBe('US');
});

test('zip+4 postal codes preserved verbatim', function () {
    $rows = (new ShippingExportParser)->parse(shippingExportFixture())->keyBy('tcgplayerOrderNumber');

    expect($rows['623394E9-ZIP4-PLUS']->postalCode)->toBe('75569-3016');
});

test('empty address2 / tracking / carrier produce null, not empty string', function () {
    $rows = (new ShippingExportParser)->parse(shippingExportFixture())->keyBy('tcgplayerOrderNumber');

    $thomas = $rows['623394E9-23CAFE-565FC'];
    expect($thomas->address2)->toBeNull();
    expect($thomas->trackingNumber)->toBeNull();
    expect($thomas->carrier)->toBeNull();

    $nick = $rows['623394E9-805EEF-0F0CC'];
    expect($nick->address2)->toBe('Unit B');
    expect($nick->trackingNumber)->toBe('9405511899223344556677');
    expect($nick->carrier)->toBe('USPS');
});

test('item_count parses to int and product_weight to float', function () {
    $rows = (new ShippingExportParser)->parse(shippingExportFixture())->keyBy('tcgplayerOrderNumber');

    expect($rows['623394E9-23CAFE-565FC']->itemCount)->toBeInt()->toBe(1);
    expect($rows['623394E9-23CAFE-565FC']->productWeight)->toBeFloat()->toBe(0.07);
    expect($rows['623394E9-ZIP4-PLUS']->itemCount)->toBe(2);
});

test('uppercases tcgplayer_order_number on every row', function () {
    $rows = (new ShippingExportParser)->parse(shippingExportFixture());
    foreach ($rows as $row) {
        expect($row->tcgplayerOrderNumber)->toBe(strtoupper($row->tcgplayerOrderNumber));
    }
});

test('missing required header raises domain exception', function () {
    $tmp = sys_get_temp_dir().'/shipping-no-header.csv';
    file_put_contents($tmp, <<<'CSV'
Order #,FirstName,LastName,Address1
"623394E9-AAA-BBB","A","B","123 Main"
CSV);

    expect(fn () => (new ShippingExportParser)->parse($tmp))
        ->toThrow(InvalidShippingExportException::class, 'Order Date');

    @unlink($tmp);
});

test('invalid Order Date raises domain exception with row number', function () {
    $tmp = sys_get_temp_dir().'/shipping-bad-date.csv';
    file_put_contents($tmp, <<<'CSV'
Order #,FirstName,LastName,Address1,Address2,City,State,PostalCode,Country,Order Date,Product Weight,Shipping Method,Item Count,Value Of Products,Shipping Fee Paid,Tracking #,Carrier
"623394E9-AAA-BBB","A","B","123 Main","","Anywhere","CA","99999","US","not-a-date","0.07","Standard","1","6.90","1.99","",""
CSV);

    expect(fn () => (new ShippingExportParser)->parse($tmp))
        ->toThrow(InvalidShippingExportException::class, 'Order Date');

    @unlink($tmp);
});
