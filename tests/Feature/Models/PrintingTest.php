<?php

use App\Models\Card;
use App\Models\Printing;
use Illuminate\Database\QueryException;

test('factory creates a printing attached to a card', function () {
    $printing = Printing::factory()->create();

    expect($printing->card)->toBeInstanceOf(Card::class);
    expect($printing->finish)->toBe('non-foil');
    expect($printing->market_price)->toBeInt();
    expect($printing->low_price)->toBeInt();
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
        'market_price' => null,
        'low_price' => null,
    ]);

    expect($printing->refresh()->tcgplayer_id)->toBeNull();
    expect($printing->justtcg_id)->toBeNull();
    expect($printing->other_ids)->toBe(['scryfall' => 'abc']);
    expect($printing->image_url)->toBeNull();
    expect($printing->market_price)->toBeNull();
    expect($printing->low_price)->toBeNull();
});

test('set accessor resolves through the card', function () {
    $printing = Printing::factory()->create();

    expect($printing->set->is($printing->card->set))->toBeTrue();
});
