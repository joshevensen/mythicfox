<?php

use Spatie\Browsershot\Browsershot;

test('Browsershot autoloads', function () {
    $instance = Browsershot::url('https://example.com');

    expect($instance)->toBeInstanceOf(Browsershot::class);
})->group('dependencies');
