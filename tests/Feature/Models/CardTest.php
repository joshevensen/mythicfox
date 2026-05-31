<?php

use App\Models\Card;
use App\Models\Set;
use Illuminate\Database\QueryException;

test('factory creates a card attached to a set', function () {
    $card = Card::factory()->create();

    expect($card->set)->toBeInstanceOf(Set::class);
    expect($card->market_price)->toBeInt();
    expect($card->low_price)->toBeInt();
});

test('tcgplayer_id uniqueness is enforced', function () {
    Card::factory()->create(['tcgplayer_id' => 12345]);

    expect(fn () => Card::factory()->create(['tcgplayer_id' => 12345]))
        ->toThrow(QueryException::class);
});

test('market_price and low_price accept null', function () {
    $card = Card::factory()->create([
        'market_price' => null,
        'low_price' => null,
    ]);

    expect($card->market_price)->toBeNull();
    expect($card->low_price)->toBeNull();
});

test('condition string round-trips verbatim with case and spaces preserved', function () {
    $card = Card::factory()->create([
        'condition' => 'Near Mint Unlimited Edition Rainbow Foil',
    ]);

    expect($card->refresh()->condition)->toBe('Near Mint Unlimited Edition Rainbow Foil');
});

test('set hasMany cards relation resolves', function () {
    $set = Set::factory()->create();
    Card::factory()->count(3)->create(['set_id' => $set->id]);

    expect($set->cards()->count())->toBe(3);
});
