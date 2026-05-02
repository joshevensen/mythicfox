<?php

use App\Support\FilePath;
use Illuminate\Support\Carbon;

test('path uses type, purpose, and current year/month', function () {
    Carbon::setTestNow('2026-03-15 10:00:00');

    $path = FilePath::build('imports', 'orders', 'OrderList.csv');

    expect($path)->toStartWith('imports/orders/2026/03/');
    expect($path)->toEndWith('-orderlist.csv');

    Carbon::setTestNow();
});

test('path includes a ULID between purpose and slug', function () {
    $path = FilePath::build('imports', 'orders', 'OrderList.csv');

    preg_match('#^imports/orders/\d{4}/\d{2}/([0-9A-Z]{26})-orderlist\.csv$#', $path, $m);
    expect($m[1] ?? null)->not->toBeNull();
});

test('slug is derived from filename', function () {
    $path = FilePath::build('imports', 'pricing', 'TCGplayer Custom Pricing Export.csv');

    expect($path)->toEndWith('-tcgplayer-custom-pricing-export.csv');
});

test('extension is preserved', function () {
    $pdf = FilePath::build('imports', 'orders', 'PackingSlips.pdf');
    $csv = FilePath::build('exports', 'pricing', 'mythic-fox-pricing.csv');

    expect($pdf)->toEndWith('.pdf');
    expect($csv)->toEndWith('.csv');
});

test('special characters in original filename are normalized', function () {
    $path = FilePath::build('imports', 'orders', 'Order #623394E9 (final).csv');

    preg_match('#-([a-z0-9-]+)\.csv$#', $path, $m);
    expect($m[1])->toBe('order-623394e9-final');
});

test('extensionless input falls back to bin', function () {
    $path = FilePath::build('imports', 'orders', 'noextension');

    expect($path)->toEndWith('.bin');
});

test('empty filename falls back to upload slug', function () {
    $path = FilePath::build('imports', 'orders', '.csv');

    expect($path)->toContain('-upload.csv');
});
