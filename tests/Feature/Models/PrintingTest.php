<?php

use App\Models\Card;
use App\Models\Printing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

const PRINTING_PRICE_COLUMNS = [
    'nm_market_price',
    'nm_low_price',
    'lp_market_price',
    'lp_low_price',
    'mp_market_price',
    'mp_low_price',
];

test('printings stores market and low prices per condition', function () {
    expect(Schema::hasColumns('printings', PRINTING_PRICE_COLUMNS))->toBeTrue();
    expect(Schema::hasColumn('printings', 'market_price'))->toBeFalse();
    expect(Schema::hasColumn('printings', 'low_price'))->toBeFalse();
});

test('factory creates a printing attached to a card', function () {
    $printing = Printing::factory()->create();

    expect($printing->card)->toBeInstanceOf(Card::class);
    expect($printing->finish)->toBe('non-foil');

    foreach (PRINTING_PRICE_COLUMNS as $column) {
        expect($printing->{$column})->toBeInt();
    }
});

test('tcgplayer_id uniqueness is enforced for non-null provider ids', function () {
    Printing::factory()->create(['tcgplayer_id' => 12345]);

    expect(fn () => Printing::factory()->create(['tcgplayer_id' => 12345]))
        ->toThrow(QueryException::class);
});

test('one printing per card and finish is enforced', function () {
    $card = Card::factory()->create();
    Printing::factory()->create(['card_id' => $card->id, 'finish' => 'foil']);

    expect(fn () => Printing::factory()->create(['card_id' => $card->id, 'finish' => 'foil']))
        ->toThrow(QueryException::class);
});

test('nullable price and provider fields round-trip', function () {
    $printing = Printing::factory()->create([
        'tcgplayer_id' => null,
        'justtcg_id' => null,
        'other_ids' => ['scryfall' => 'abc'],
        'image_url' => null,
        ...array_fill_keys(PRINTING_PRICE_COLUMNS, null),
    ]);

    expect($printing->refresh()->tcgplayer_id)->toBeNull();
    expect($printing->justtcg_id)->toBeNull();
    expect($printing->other_ids)->toBe(['scryfall' => 'abc']);
    expect($printing->image_url)->toBeNull();

    foreach (PRINTING_PRICE_COLUMNS as $column) {
        expect($printing->{$column})->toBeNull();
    }
});

test('set accessor resolves through the card', function () {
    $printing = Printing::factory()->create();

    expect($printing->set->is($printing->card->set))->toBeTrue();
});

test('withPricing sets only the given condition pair', function () {
    $printing = Printing::factory()->withoutPricing()->withPricing('lp', 300, 250)->create();

    expect($printing->lp_market_price)->toBe(300);
    expect($printing->lp_low_price)->toBe(250);
    expect($printing->nm_market_price)->toBeNull();
    expect($printing->mp_market_price)->toBeNull();
});

test('withoutPricing nulls every condition price', function () {
    $printing = Printing::factory()->withoutPricing()->create();

    foreach (PRINTING_PRICE_COLUMNS as $column) {
        expect($printing->{$column})->toBeNull();
    }
});

test('withPricing rejects unknown conditions', function () {
    expect(fn () => Printing::factory()->withPricing('hp', 1, 1))
        ->toThrow(InvalidArgumentException::class);
});
