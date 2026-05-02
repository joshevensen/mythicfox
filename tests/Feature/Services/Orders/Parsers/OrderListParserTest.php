<?php

use App\Exceptions\OrderImport\InvalidOrderListException;
use App\Services\Orders\Parsers\OrderListParser;
use Carbon\CarbonImmutable;

function orderListFixture(): string
{
    return base_path('tests/fixtures/orders/orderlist-sample.csv');
}

test('parses every row from the canonical fixture', function () {
    $rows = (new OrderListParser)->parse(orderListFixture());

    expect($rows)->toHaveCount(3);
});

test('uppercases tcgplayer_order_number on every row', function () {
    $rows = (new OrderListParser)->parse(orderListFixture());

    expect($rows->pluck('tcgplayerOrderNumber')->all())->toContain(
        '623394E9-23CAFE-565FC',
        '623394E9-805EEF-0F0CC',
        '623394E9-AAAAAA-BBBBB',
    );
});

test('parses status, buyer name, and Buyer Paid', function () {
    $rows = (new OrderListParser)->parse(orderListFixture());

    $first = $rows->first();
    expect($first->tcgplayerStatus)->toBe('Completed - Paid');
    expect($first->buyerName)->toBe('Thomas Birch');
    expect($first->buyerPaid)->toBeTrue();

    $canceled = $rows->last();
    expect($canceled->tcgplayerStatus)->toBe('Canceled');
    expect($canceled->buyerPaid)->toBeFalse();
});

test('parses natural-language Order Date with single-digit and two-digit days', function () {
    $rows = (new OrderListParser)->parse(orderListFixture());

    $rowsByOrder = $rows->keyBy('tcgplayerOrderNumber');

    expect($rowsByOrder['623394E9-23CAFE-565FC']->orderDate)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($rowsByOrder['623394E9-23CAFE-565FC']->orderDate->toDateString())->toBe('2025-11-14');

    expect($rowsByOrder['623394E9-AAAAAA-BBBBB']->orderDate->toDateString())->toBe('2025-12-07');
});

test('converts decimal money columns to integer cents exactly', function () {
    $rows = (new OrderListParser)->parse(orderListFixture());
    $first = $rows->first();

    expect($first->productAmount)->toBe(690);
    expect($first->shippingAmount)->toBe(199);
    expect($first->totalAmount)->toBe(889);
});

test('handles 0.20 / 10.11 / 1234.56 cents conversion exactly via inline fixture', function () {
    $tmp = sys_get_temp_dir().'/orderlist-cents.csv';
    file_put_contents($tmp, <<<'CSV'
Order #,Buyer Name,Order Date,Status,Shipping Type,Product Amt,Shipping Amt,Total Amt,Buyer Paid,Carrier Information
"623394E9-001-001","B1","Friday, 1 January 2026","Completed - Paid","Standard","0.20","0.00","0.20","True"
"623394E9-002-002","B2","Friday, 1 January 2026","Completed - Paid","Standard","10.11","0.00","10.11","True"
"623394E9-003-003","B3","Friday, 1 January 2026","Completed - Paid","Standard","1234.56","0.00","1234.56","True"
CSV);

    $rows = (new OrderListParser)->parse($tmp)->pluck('totalAmount')->all();
    expect($rows)->toBe([20, 1011, 123456]);

    @unlink($tmp);
});

test('header with 10 columns and data rows with 9 is tolerated', function () {
    // The canonical fixture is exactly this case; if it parsed, the test passes.
    $rows = (new OrderListParser)->parse(orderListFixture());
    expect($rows)->not->toBeEmpty();
});

test('missing required header raises InvalidOrderListException', function () {
    $tmp = sys_get_temp_dir().'/orderlist-missing-header.csv';
    file_put_contents($tmp, <<<'CSV'
Order #,Buyer Name,Order Date,Status,Shipping Type,Product Amt,Shipping Amt,Total Amt
"623394E9-001-001","B1","Friday, 1 January 2026","Completed - Paid","Standard","0.20","0.00","0.20"
CSV);

    expect(fn () => (new OrderListParser)->parse($tmp))
        ->toThrow(InvalidOrderListException::class, 'Buyer Paid');

    @unlink($tmp);
});

test('non-numeric money column raises InvalidOrderListException with row number', function () {
    $tmp = sys_get_temp_dir().'/orderlist-bad-money.csv';
    file_put_contents($tmp, <<<'CSV'
Order #,Buyer Name,Order Date,Status,Shipping Type,Product Amt,Shipping Amt,Total Amt,Buyer Paid,Carrier Information
"623394E9-001-001","B1","Friday, 1 January 2026","Completed - Paid","Standard","not-a-number","0.00","0.20","True"
CSV);

    expect(fn () => (new OrderListParser)->parse($tmp))
        ->toThrow(InvalidOrderListException::class, 'Product Amt');

    @unlink($tmp);
});
