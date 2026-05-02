<?php

use Smalot\PdfParser\Parser;

test('Smalot PdfParser autoloads', function () {
    $parser = new Parser;

    expect($parser)->toBeInstanceOf(Parser::class);
})->group('dependencies');
