<?php

use App\Services\Catalog\PriceCalculator;
use App\Services\Catalog\PricingRules;

function defaultRules(): PricingRules
{
    return new PricingRules(basePrice: 25, highPrice: 1000, marketOffset: 0, highOffset: 15);
}

test('example: bulk row floored at base_price', function () {
    expect(PriceCalculator::calculate(10, 5, defaultRules()))->toBe(25);
});

test('example: bulk row uses market when market > low', function () {
    expect(PriceCalculator::calculate(300, 250, defaultRules()))->toBe(300);
});

test('example: bulk row uses low when low > market', function () {
    expect(PriceCalculator::calculate(300, 350, defaultRules()))->toBe(350);
});

test('example: high-segment row uses min input minus high_offset', function () {
    expect(PriceCalculator::calculate(1200, 1050, defaultRules()))->toBe(1035);
});

test('example: high-segment row with null low falls back to market', function () {
    expect(PriceCalculator::calculate(1200, null, defaultRules()))->toBe(1185);
});

test('null market price returns null', function () {
    expect(PriceCalculator::calculate(null, 250, defaultRules()))->toBeNull();
    expect(PriceCalculator::calculate(null, null, defaultRules()))->toBeNull();
});

test('null low_price falls back to market for both inputs', function () {
    // market=500, low=null → low becomes 500, max(500,500)=500, 500>=25, 500-0=500
    expect(PriceCalculator::calculate(500, null, defaultRules()))->toBe(500);
});

test('input exactly at high_price boundary stays in bulk segment', function () {
    // market=1000, low=1000: 1000 > 1000 is false, so bulk segment.
    expect(PriceCalculator::calculate(1000, 1000, defaultRules()))->toBe(1000);
});

test('input one cent above high_price drops to high segment', function () {
    // market=1001, low=1001: 1001>1000 true, min(1001,1001)=1001, 1001>1000 → 1001-15=986
    expect(PriceCalculator::calculate(1001, 1001, defaultRules()))->toBe(986);
});

test('input exactly at base_price uses market_offset branch', function () {
    // market=25, low=25: 25>1000 false → bulk, max(25,25)=25, 25>=25 → 25-0=25.
    expect(PriceCalculator::calculate(25, 25, defaultRules()))->toBe(25);
});

test('input below base_price floors at base_price', function () {
    // market=20, low=10: bulk segment, max=20, 20<25 → base_price=25.
    expect(PriceCalculator::calculate(20, 10, defaultRules()))->toBe(25);
});

test('high-segment offset that would dip below base_price is clamped', function () {
    $aggressiveOffset = new PricingRules(basePrice: 25, highPrice: 1000, marketOffset: 0, highOffset: 999);
    // market=1001, low=1001: high segment, 1001-999=2, clamp up to base_price=25.
    expect(PriceCalculator::calculate(1001, 1001, $aggressiveOffset))->toBe(25);
});

test('non-default market_offset reduces bulk price', function () {
    $rules = new PricingRules(basePrice: 25, highPrice: 1000, marketOffset: 50, highOffset: 15);
    // market=300, low=250: bulk, max=300, 300>=25 → 300-50=250.
    expect(PriceCalculator::calculate(300, 250, $rules))->toBe(250);
});
