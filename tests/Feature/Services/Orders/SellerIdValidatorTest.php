<?php

use App\Exceptions\OrderImport\WrongSellerException;
use App\Models\Order;
use App\Services\Orders\OrderImporter;
use App\Services\Orders\OrderImportInput;
use App\Services\Orders\SellerIdValidator;
use Illuminate\Support\Facades\Storage;

test('matches case-insensitive seller ID prefix', function () {
    config(['services.tcgplayer.seller_id' => '623394e9']);
    $v = new SellerIdValidator;

    expect($v->isValid('623394E9-23CAFE-565FC'))->toBeTrue();
    expect($v->isValid('623394e9-23cafe-565fc'))->toBeTrue();
});

test('rejects an alien seller ID', function () {
    config(['services.tcgplayer.seller_id' => '623394e9']);
    $v = new SellerIdValidator;

    expect($v->isValid('ABCD1234-XXX-YYY'))->toBeFalse();
});

test('assertValid throws WrongSellerException for alien seller', function () {
    config(['services.tcgplayer.seller_id' => '623394e9']);
    $v = new SellerIdValidator;

    expect(fn () => $v->assertValid('ABCD1234-XXX-YYY'))
        ->toThrow(WrongSellerException::class);
});

test('empty configured seller ID skips the check entirely', function () {
    config(['services.tcgplayer.seller_id' => '']);
    $v = new SellerIdValidator;

    expect($v->isValid('whatever-XXX'))->toBeTrue();
    expect(fn () => $v->assertValid('totally-bogus-id'))->not->toThrow(Throwable::class);
});

test('null configured seller ID skips the check entirely', function () {
    config(['services.tcgplayer.seller_id' => null]);
    $v = new SellerIdValidator;

    expect($v->isValid('whatever-XXX'))->toBeTrue();
});

test('order number with no hyphen segment is invalid', function () {
    config(['services.tcgplayer.seller_id' => '623394e9']);
    $v = new SellerIdValidator;

    expect($v->isValid('NOTHYPHENATED'))->toBeFalse();
    expect($v->isValid(''))->toBeFalse();
});

test('OrderImporter surfaces seller-id mismatch as a parser error', function () {
    config(['services.tcgplayer.seller_id' => '623394e9']);

    $tmp = sys_get_temp_dir().'/orderlist-alien-seller.csv';
    file_put_contents($tmp, <<<'CSV'
Order #,Buyer Name,Order Date,Status,Shipping Type,Product Amt,Shipping Amt,Total Amt,Buyer Paid,Carrier Information
"ALIEN1234-XX-YY","Bad Buyer","Friday, 1 January 2026","Completed - Paid","Standard","6.9","1.99","8.89","True"
CSV);

    Storage::fake('local');

    $result = (new OrderImporter)->import(
        new OrderImportInput(orderListPath: $tmp)
    );

    expect($result->ordersInserted)->toBe(0);
    expect(implode(' ', $result->errors))->toContain('ALIEN1234');
    expect(Order::count())->toBe(0);

    @unlink($tmp);
});
