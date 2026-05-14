<?php

use App\Services\SellerStats\TcgplayerStorefrontParser;

test('parser extracts rating, review count, and feedback from fixture HTML', function () {
    $html = file_get_contents(__DIR__.'/../../fixtures/tcgplayer-storefront.html');

    $result = (new TcgplayerStorefrontParser)->parse($html);

    expect($result->rating)->toBe(4.9)
        ->and($result->reviewCount)->toBe(1234)
        ->and($result->feedback)->toBeArray()->toHaveCount(3)
        ->and($result->feedback[0]['text'])->toContain('Great seller')
        ->and($result->feedback[0]['rating'])->toBe(5)
        ->and($result->feedback[0]['author'])->toBe('john_d');
});

test('parser returns null rating when element is absent', function () {
    $result = (new TcgplayerStorefrontParser)->parse('<html><body></body></html>');

    expect($result->rating)->toBeNull()
        ->and($result->reviewCount)->toBeNull()
        ->and($result->feedback)->toBeNull();
});

test('parser returns null feedback when no feedback items exist', function () {
    $html = <<<'HTML'
    <html><body>
      <span class="seller-rating__average">4.8</span>
      <span class="seller-rating__count">500 ratings</span>
    </body></html>
    HTML;

    $result = (new TcgplayerStorefrontParser)->parse($html);

    expect($result->rating)->toBe(4.8)
        ->and($result->reviewCount)->toBe(500)
        ->and($result->feedback)->toBeNull();
});

test('parser handles review count with comma-formatted numbers', function () {
    $html = <<<'HTML'
    <html><body>
      <span class="seller-rating__average">5.0</span>
      <span class="seller-rating__count">12,345 ratings</span>
    </body></html>
    HTML;

    $result = (new TcgplayerStorefrontParser)->parse($html);

    expect($result->reviewCount)->toBe(12345);
});
